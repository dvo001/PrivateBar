<?php

namespace Tests\Unit;

use App\Domain\Updates\ReleaseVerifier;
use Tests\TestCase;

final class ReleaseVerifierTest extends TestCase
{
    public function test_signed_manifest_validates_and_tampering_is_rejected(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $public = openssl_pkey_get_details($key)['key'];
        $data = ['version' => '1.0.0', 'url' => 'https://example.test/release.tar', 'sha256' => str_repeat('a', 64), 'bytes' => 1000, 'php' => '8.3', 'api_min' => 1, 'api_max' => 1, 'backward_compatible_migrations' => true, 'checks_passed' => true];
        $payload = json_encode($data);
        openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);
        $envelope = ['payload' => base64_encode($payload), 'signature' => base64_encode($signature)];
        self::assertSame('1.0.0', (new ReleaseVerifier)->verify($envelope, $public)['version']);
        $envelope['payload'] = base64_encode(str_replace('1.0.0', '2.0.0', $payload));
        $this->expectException(\RuntimeException::class);
        (new ReleaseVerifier)->verify($envelope, $public);
    }
}
