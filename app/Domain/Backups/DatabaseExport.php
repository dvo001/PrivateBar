<?php

namespace App\Domain\Backups;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DatabaseExport
{
    // Laufzeitdaten dürfen nach einer Wiederherstellung nicht weiterlaufen.
    private const EMPTY_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function create(): string
    {
        if (config('privatebar.mode') !== 'cloud' || ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Datenbankexport ist nur auf Cyon mit MariaDB verfügbar.');
        }
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Export benötigt eine eigene Datenbanktransaktion.');
        }
        $tables = DB::select('SHOW FULL TABLES');
        $names = [];
        foreach ($tables as $table) {
            $values = array_values((array) $table);
            if ($values[1] !== 'BASE TABLE') {
                throw new RuntimeException('Export unterstützt ausschliesslich Anwendungstabellen.');
            }
            $names[] = (string) $values[0];
        }
        sort($names);
        foreach (DB::select('SHOW TABLE STATUS') as $table) {
            if ($table->Engine !== 'InnoDB') {
                throw new RuntimeException('Ein konsistenter Export benötigt InnoDB-Tabellen.');
            }
        }
        $directory = storage_path('app/private/database-exports');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Exportverzeichnis nicht verfügbar.');
        }
        $path = tempnam($directory, 'export-');
        if ($path === false) {
            throw new RuntimeException('Exportdatei konnte nicht angelegt werden.');
        }
        chmod($path, 0600);
        $file = fopen($path, 'wb');
        if ($file === false) {
            unlink($path);
            throw new RuntimeException('Exportdatei nicht verfügbar.');
        }
        $transactionStarted = false;
        try {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::beginTransaction();
            $transactionStarted = true;
            // Der erste InnoDB-Lesezugriff fixiert den Snapshot für alle Daten.
            DB::table('users')->count();
            $this->write($file, '-- PrivateBar Datenbankexport '.config('privatebar.version').' / '.gmdate('c')."\n"
                ."-- Import nur in eine leere Datenbank. Bilder und .env separat sichern.\n"
                ."-- Sitzungen, Cache und Warteschlangen bleiben leer.\n"
                ."SET NAMES utf8mb4;\nSET TIME_ZONE='+00:00';\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            foreach ($names as $name) {
                $table = $this->identifier($name);
                $ddl = array_values((array) DB::selectOne('SHOW CREATE TABLE '.$table))[1];
                $this->write($file, $ddl.";\n\n");
            }
            $this->write($file, "START TRANSACTION;\n");
            foreach ($names as $name) {
                if (in_array($name, self::EMPTY_TABLES, true)) {
                    continue;
                }
                $table = $this->identifier($name);
                $columns = [];
                $binaryColumns = [];
                foreach (DB::select('SHOW FULL COLUMNS FROM '.$table) as $column) {
                    if (! str_contains($column->Extra, 'GENERATED')) {
                        $columns[] = $column->Field;
                        $binaryColumns[$column->Field] = preg_match('/^(?:varbinary|binary|tinyblob|blob|mediumblob|longblob|bit)\b/i', $column->Type) === 1;
                    }
                }
                $query = DB::table($name)->select($columns);
                foreach (DB::select('SHOW INDEX FROM '.$table." WHERE Key_name = 'PRIMARY'") as $index) {
                    $query->orderBy($index->Column_name);
                }
                $prefix = 'INSERT INTO '.$table.' ('.implode(',', array_map($this->identifier(...), $columns)).') VALUES (';
                // Begrenzte Batches statt eines gepufferten Cursors über die ganze DB.
                for ($offset = 0; ; $offset += 100) {
                    $rows = (clone $query)->offset($offset)->limit(100)->get();
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columns as $column) {
                            $value = $row->{$column};
                            // Gespeicherte Remember-me-Zugänge nach Restore neu anmelden.
                            if ($name === 'users' && $column === 'remember_token') {
                                $value = null;
                            }
                            $quoted = $value === null ? 'NULL'
                                : ($binaryColumns[$column] ? "X'".bin2hex((string) $value)."'" : DB::connection()->getPdo()->quote((string) $value));
                            if ($quoted === false) {
                                throw new RuntimeException('Datenwert konnte nicht exportiert werden.');
                            }
                            $values[] = $quoted;
                        }
                        $this->write($file, $prefix.implode(',', $values).");\n");
                    }
                    if ($rows->count() < 100) {
                        break;
                    }
                }
            }
            $this->write($file, "INSERT INTO `local_settings` (`key`,`value`,`created_at`,`updated_at`) VALUES ('maintenance','true',UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE `value`='true',`updated_at`=UTC_TIMESTAMP();\n");
            $this->write($file, "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n-- Export vollständig.\n");
            DB::commit();
            $transactionStarted = false;
            if (! fflush($file)) {
                throw new RuntimeException('Export konnte nicht vollständig geschrieben werden.');
            }
            fclose($file);
            clearstatcache(true, $path);

            return $path;
        } catch (Throwable $error) {
            if ($transactionStarted) {
                DB::rollBack();
            }
            fclose($file);
            unlink($path);
            throw $error;
        }
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /** @param resource $file */
    private function write($file, string $sql): void
    {
        if (fwrite($file, $sql) !== strlen($sql)) {
            throw new RuntimeException('Export konnte nicht vollständig geschrieben werden.');
        }
    }
}
