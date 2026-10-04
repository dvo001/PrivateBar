<?php

namespace Tests\Feature;

use App\Domain\Backups\DatabaseExport;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DatabaseExportRoundTripTest extends TestCase
{
    use DatabaseMigrations;

    public function test_export_restores_schema_data_and_constraints_into_an_empty_mariadb(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            self::markTestSkipped('SQL-Roundtrip benötigt die isolierte MariaDB-Testdatenbank.');
        }
        config(['privatebar.mode' => 'cloud']);
        $this->seed();
        $user = User::forceCreate(['uuid' => (string) Str::uuid(), 'name' => "Zürich ' \\ 😀", 'email' => 'roundtrip@example.test',
            'email_verified_at' => now(), 'password' => Hash::make('export-test-password'), 'remember_token' => 'old-remember-token']);
        DB::table('sessions')->insert(['id' => 'old-session', 'payload' => 'must-not-restore', 'last_activity' => time()]);
        DB::table('cache')->insert(['key' => 'old-cache', 'value' => 'must-not-restore', 'expiration' => time() + 600]);
        app(Settings::class)->set('recipe_import_enabled', false);
        DB::statement('DROP TABLE IF EXISTS export_test_values');
        DB::statement('CREATE TABLE export_test_values (id BIGINT PRIMARY KEY, amount DECIMAL(10,3), text_value LONGTEXT, bytes_value VARBINARY(20), doubled BIGINT GENERATED ALWAYS AS (id*2) STORED) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        for ($i = 0; $i < 105; $i++) {
            DB::table('export_test_values')->insert(['id' => $i, 'amount' => '12.345', 'text_value' => $i === 0 ? "quote' slash\\ newline\n null\0 😀" : null,
                'bytes_value' => "\0\xff\x01"]);
        }
        $expected = [];
        foreach (['users', 'recipes', 'recipe_ingredients', 'ingredients', 'products', 'sync_events', 'devices', 'migrations'] as $table) {
            $expected[$table] = DB::table($table)->count();
        }
        $source = DB::connection();
        $config = $source->getConfig();
        $target = 'privatebar_export_test_'.bin2hex(random_bytes(5));
        $source->statement('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        config(['database.connections.export_roundtrip' => array_replace($config, ['database' => $target])]);
        $path = null;
        try {
            // Eine Änderung nach Snapshotbeginn muss aus dem Dump ausgeschlossen sein.
            $writer = new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password']);
            $mutated = false;
            DB::listen(function ($event) use ($writer, $user, &$mutated) {
                if (! $mutated && str_contains(strtolower($event->sql), 'count(*)') && str_contains($event->sql, 'users')) {
                    $mutated = true;
                    $statement = $writer->prepare('UPDATE users SET name=? WHERE id=?');
                    $statement->execute(['Changed after snapshot', $user->id]);
                }
            });
            $path = app(DatabaseExport::class)->create();
            self::assertTrue($mutated);
            self::assertTrue(mb_check_encoding(file_get_contents($path), 'UTF-8'));
            self::assertStringNotContainsString('must-not-restore', file_get_contents($path));
            $restored = DB::connection('export_roundtrip');
            $restored->getPdo()->exec(file_get_contents($path));
            foreach ($expected as $table => $count) {
                self::assertSame($count, $restored->table($table)->count(), $table);
            }
            $restoredUser = $restored->table('users')->first();
            self::assertSame($user->name, $restoredUser->name);
            self::assertTrue(Hash::check('export-test-password', $restoredUser->password));
            self::assertNull($restoredUser->remember_token);
            self::assertSame(0, $restored->table('sessions')->count());
            self::assertSame(0, $restored->table('cache')->count());
            self::assertSame('true', $restored->table('local_settings')->where('key', 'maintenance')->value('value'));
            self::assertSame('false', $restored->table('local_settings')->where('key', 'recipe_import_enabled')->value('value'));
            self::assertSame(105, $restored->table('export_test_values')->count());
            $row = $restored->table('export_test_values')->where('id', 0)->first();
            self::assertSame('12.345', $row->amount);
            self::assertSame("quote' slash\\ newline\n null\0 😀", $row->text_value);
            self::assertSame("\0\xff\x01", $row->bytes_value);
            self::assertSame(208, (int) $restored->table('export_test_values')->where('id', 104)->value('doubled'));
            self::assertGreaterThan(0, count($restored->select('SHOW CREATE TABLE recipe_ingredients')));
            try {
                $restored->table('recipe_ingredients')->insert(['recipe_id' => (string) Str::uuid(), 'ingredient_id' => (string) Str::uuid(), 'role' => 'required', 'position' => 0]);
                self::fail('Fremdschlüssel fehlen nach dem Import.');
            } catch (QueryException $error) {
                self::assertSame('23000', $error->errorInfo[0]);
            }
            $download = $this->actingAs($user)->post('/einstellungen/datenbank/export', ['password' => 'export-test-password']);
            $download->assertOk()->assertDownload();
            $downloadPath = $download->baseResponse->getFile()->getPathname();
            ob_start();
            $download->baseResponse->sendContent();
            $downloaded = ob_get_clean();
            self::assertStringContainsString('-- Export vollständig.', $downloaded);
            self::assertFileDoesNotExist($downloadPath);
            self::assertFalse(app(Settings::class)->maintenance());
        } finally {
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
            DB::purge('export_roundtrip');
            $source->statement('DROP DATABASE `'.$target.'`');
            $source->statement('DROP TABLE IF EXISTS export_test_values');
        }
    }

    public function test_read_failure_rolls_back_snapshot_and_deletes_incomplete_export(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            self::markTestSkipped('Fehlerbehandlung benötigt die isolierte MariaDB-Testdatenbank.');
        }
        config(['privatebar.mode' => 'cloud']);
        $directory = storage_path('app/private/database-exports');
        $before = glob($directory.'/export-*');
        DB::listen(function ($event) {
            if (str_contains(strtolower($event->sql), 'count(*)') && str_contains($event->sql, 'users')) {
                throw new \RuntimeException('Simulierter Lesefehler');
            }
        });
        try {
            app(DatabaseExport::class)->create();
            self::fail('Export darf nicht erfolgreich sein.');
        } catch (\RuntimeException $error) {
            self::assertSame('Simulierter Lesefehler', $error->getMessage());
        }
        self::assertSame(0, DB::transactionLevel());
        self::assertSame($before, glob($directory.'/export-*'));
    }
}
