<?php

namespace Tests\Feature;

use App\Domain\Settings\CloudSetup;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class CloudSetupTest extends TestCase
{
    use RefreshDatabase;

    private function input(): array
    {
        return ['email' => 'member@example.test', 'name' => 'Mitglied', 'password' => 'a-long-test-password', 'device_name' => 'Hausbar Pi'];
    }

    public function test_retry_keeps_account_token_and_published_events_unchanged(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $setup = new CloudSetup;
        $token = bin2hex(random_bytes(32));
        $setup->initialize($this->input(), $token);
        $events = DB::table('sync_events')->count();
        self::assertGreaterThan(0, $events);
        $setup->initialize($this->input(), $token);
        self::assertSame(1, DB::table('users')->count());
        self::assertSame(1, DB::table('devices')->count());
        self::assertSame($events, DB::table('sync_events')->count());
        self::assertTrue(Hash::check($this->input()['password'], DB::table('users')->value('password')));
        self::assertSame(hash('sha256', $token), DB::table('devices')->value('token_hash'));
    }

    public function test_different_installation_cannot_reinitialize_accounts(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $setup = new CloudSetup;
        $setup->initialize($this->input(), bin2hex(random_bytes(32)));
        $this->expectException(RuntimeException::class);
        $setup->initialize($this->input(), bin2hex(random_bytes(32)));
    }

    public function test_publish_failure_rolls_back_accounts_and_seeds_and_allows_retry(): void
    {
        config(['privatebar.mode' => 'cloud']);
        app(Settings::class)->set('maintenance', true);
        $setup = new CloudSetup;
        $token = bin2hex(random_bytes(32));
        try {
            $setup->initialize($this->input(), $token);
            self::fail('Installation im Wartungsmodus muss abbrechen.');
        } catch (RuntimeException) {
            self::assertSame(0, DB::table('users')->count());
            self::assertSame(0, DB::table('devices')->count());
            self::assertSame(0, DB::table('recipes')->count());
        }
        app(Settings::class)->set('maintenance', false);
        $setup->initialize($this->input(), $token);
        self::assertSame(1, DB::table('users')->count());
    }

    public function test_pi_mode_is_rejected(): void
    {
        config(['privatebar.mode' => 'pi']);
        $this->expectException(RuntimeException::class);
        (new CloudSetup)->initialize($this->input(), bin2hex(random_bytes(32)));
    }

    public function test_existing_member_is_not_overwritten(): void
    {
        config(['privatebar.mode' => 'cloud']);
        User::factory()->create();
        $this->expectException(RuntimeException::class);
        (new CloudSetup)->initialize($this->input(), bin2hex(random_bytes(32)));
    }

    public function test_short_password_is_rejected_before_writing(): void
    {
        config(['privatebar.mode' => 'cloud']);
        try {
            (new CloudSetup)->initialize(array_replace($this->input(), ['password' => 'short']), bin2hex(random_bytes(32)));
            self::fail();
        } catch (RuntimeException) {
            self::assertSame(0, DB::table('users')->count());
            self::assertSame(0, DB::table('devices')->count());
        }
    }
}
