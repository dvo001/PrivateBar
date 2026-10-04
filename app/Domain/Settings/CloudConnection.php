<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class CloudConnection
{
    public function __construct(private Settings $settings) {}

    public function url(): string
    {
        return rtrim((string) $this->settings->get('cloud_url', config('privatebar.cloud_url')), '/');
    }

    public function token(): string
    {
        return $this->settings->secret('device_token') ?? (string) config('privatebar.device_token');
    }

    public static function validUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && ($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment']);
    }

    public function save(string $url, ?string $token): void
    {
        $url = rtrim($url, '/');
        if (! self::validUrl($url)) {
            throw ValidationException::withMessages(['cloud_url' => 'Bitte eine HTTPS-Adresse ohne Zugangsdaten, Suchparameter oder Fragment eingeben.']);
        }
        if ($url !== $this->url() && $this->token() !== '' && ! $token) {
            throw ValidationException::withMessages(['device_token' => 'Bei einer neuen Serveradresse bitte auch den dazugehörigen Gerätezugang eingeben.']);
        }
        $this->settings->set('cloud_url', $url);
        if ($token) {
            $this->settings->setSecret('device_token', $token);
        }
        $this->settings->set('cloud_connection_result', null);
        $this->settings->set('sync_requested', true);
    }

    public function test(): bool
    {
        if (! self::validUrl($this->url()) || $this->token() === '') {
            return false;
        }
        try {
            $response = Http::withToken($this->token())->withoutRedirecting()->connectTimeout(3)->timeout(10)
                ->get($this->url().'/api/v1/device-check');

            return $response->ok() && $response->json('authenticated') === true
                && $response->json('schema_version') === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
