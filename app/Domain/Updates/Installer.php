<?php

namespace App\Domain\Updates;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\Http;

final class Installer
{
    public function __construct(private ReleaseVerifier $verifier, private Settings $settings, private ReleaseRunner $runner) {}

    public function install(): void
    {
        $this->settings->assertRunning();
        if (config('privatebar.mode') !== 'pi' || PHP_SAPI !== 'cli') {
            throw new \RuntimeException('Installation ist nur über den lokalen Systemdienst möglich.');
        }
        $release = $this->verifier->latest();
        if (! version_compare($release['version'], config('privatebar.version'), '>')) {
            throw new \RuntimeException('Die installierte Version ist bereits aktuell.');
        }
        $root = config('privatebar.release_root', '/srv/privatebar');
        $target = $root.'/releases/'.$release['version'];
        if (! is_dir($root.'/releases') || ! is_link($root.'/current')) {
            throw new \RuntimeException('Die Release-Verzeichnisstruktur ist noch nicht eingerichtet.');
        }
        if (disk_free_space($root) < max(512 * 1024 * 1024, $release['bytes'] * 5)) {
            throw new \RuntimeException('Zu wenig freier Speicher für das Update.');
        }
        $old = realpath($root.'/current');
        $temporary = tempnam(sys_get_temp_dir(), 'privatebar-release-');
        $archive = $temporary.'.tar';
        rename($temporary, $archive);
        try {
            Http::withHeaders(config('privatebar.release_token') && parse_url($release['url'], PHP_URL_HOST) === parse_url(config('privatebar.release_manifest'), PHP_URL_HOST) ? ['Authorization' => 'Bearer '.config('privatebar.release_token'), 'Accept' => 'application/octet-stream'] : [])->connectTimeout(3)->timeout(90)->withOptions(['sink' => $archive])->get($release['url'])->throw();
            if (filesize($archive) !== $release['bytes'] || ! hash_equals($release['sha256'], hash_file('sha256', $archive))) {
                throw new \RuntimeException('Die Release-Prüfsumme stimmt nicht.');
            }
            $tar = new \PharData($archive);
            $size = 0;
            foreach (new \RecursiveIteratorIterator($tar, \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                $name = str_replace('phar://'.$archive.'/', '', $entry->getPathname());
                if ($entry->isLink() || str_contains($name, '..') || str_starts_with($name, '/') || in_array(explode('/', $name)[0], ['.env', 'storage'], true)) {
                    throw new \RuntimeException('Das Release enthält unzulässige Archivpfade.');
                }
                $size += $entry->getSize();
                if ($size > 1024 * 1024 * 1024) {
                    throw new \RuntimeException('Entpacktes Release ist zu gross.');
                }
            }
            if (file_exists($target)) {
                throw new \RuntimeException('Das Zielrelease existiert bereits. Vor einem erneuten Versuch lokal prüfen.');
            }
            mkdir($target, 0750, true);
            $tar->extractTo($target);
            if (! is_file($target.'/artisan') || ! is_file($target.'/vendor/autoload.php')) {
                throw new \RuntimeException('Das Release ist unvollständig.');
            }
            symlink($root.'/shared/.env', $target.'/.env');
            symlink($root.'/shared/storage', $target.'/storage');
            $this->settings->set('maintenance', true);
            foreach ([['migrate', '--force'], ['optimize'], ['privatebar:health']] as $args) {
                $this->runner->run($target, $args);
            }
            $link = $root.'/current.next';
            if (is_link($link)) {
                unlink($link);
            }
            symlink($target, $link);
            if (! rename($link, $root.'/current')) {
                throw new \RuntimeException('Aktivieren des Releases fehlgeschlagen.');
            }
            $this->settings->set('update_state', 'Version '.$release['version'].' installiert.');
            $this->settings->set('maintenance', false);
        } catch (\Throwable $e) {
            if ($old && realpath($root.'/current') !== $old) {
                $link = $root.'/current.rollback';
                if (is_link($link)) {
                    unlink($link);
                } symlink($old, $link);
                rename($link, $root.'/current');
            }
            // Alte Programmversion wiederherstellen; fehlgeschlagene Migration bleibt zur Prüfung gesperrt.
            $this->settings->set('update_error', 'Update fehlgeschlagen. Vorherige Programmversion aktiv; Wartungszustand direkt am Pi prüfen.');
            throw $e;
        } finally {
            @unlink($archive);
        }
    }
}
