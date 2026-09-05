<?php

namespace App\Domain\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

final class ReleaseVerifier
{
    public function verify(array $envelope, string $publicKey): array
    {
        $payload = base64_decode($envelope['payload'] ?? '', true);
        $signature = base64_decode($envelope['signature'] ?? '', true);
        if (! $payload || ! $signature || openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Die Release-Signatur ist ungültig.');
        }
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        Validator::make($data, ['version' => 'required|regex:/^\d+\.\d+\.\d+$/D', 'url' => 'required|url:https', 'sha256' => 'required|regex:/^[a-f0-9]{64}$/D', 'bytes' => 'required|integer|min:1|max:268435456', 'php' => 'required|in:8.3', 'api_min' => 'required|integer|min:1', 'api_max' => 'required|integer|min:1', 'backward_compatible_migrations' => 'accepted', 'checks_passed' => 'accepted'])->validate();
        if ($data['api_min'] > 1 || $data['api_max'] < 1) {
            throw new \RuntimeException('Dieses Release ist nicht mit API v1 kompatibel.');
        }

        return $data;
    }

    public function latest(): array
    {
        $url = config('privatebar.release_manifest');
        $key = config('privatebar.release_public_key');
        if (! $url || ! str_starts_with($url, 'https://') || ! $key) {
            throw new \RuntimeException('Release-Adresse und vertrauenswürdiger öffentlicher Signaturschlüssel sind noch nicht eingerichtet.');
        }
        $envelope = Http::withHeaders(config('privatebar.release_token') ? ['Authorization' => 'Bearer '.config('privatebar.release_token'), 'Accept' => 'application/octet-stream'] : [])->connectTimeout(3)->timeout(10)->get($url)->throw()->json();

        return $this->verify($envelope, str_replace('\\n', "\n", $key));
    }
}
