<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Access\MemberLinks;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.pin_hash' => Hash::make('123456'), 'privatebar.mode' => 'pi']);
    }

    public function test_pin_lock_survives_new_guard_and_expires_after_five_minutes(): void
    {
        for ($n = 0; $n < 7; $n++) {
            self::assertFalse((new AccessGuard)->pin('000000'));
        }
        try {
            (new AccessGuard)->pin('000000');
            self::fail();
        } catch (ValidationException) {
        }
        try {
            (new AccessGuard)->pin('123456');
            self::fail();
        } catch (ValidationException) {
        }
        $this->travel(5)->minutes();
        self::assertTrue((new AccessGuard)->pin('123456'));
    }

    public function test_online_two_failures_lock_email_and_ip_pair(): void
    {
        config(['privatebar.mode' => 'cloud']);
        User::factory()->create(['email' => 'member@example.test', 'password' => Hash::make('long-password')]);
        for ($n = 0; $n < 2; $n++) {
            $this->post('/anmelden', ['email' => 'member@example.test', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post('/anmelden', ['email' => 'member@example.test', 'password' => 'long-password'])->assertSessionHasErrors();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post('/anmelden', ['email' => 'member@example.test', 'password' => 'long-password'])->assertRedirect('/');
    }

    public function test_invitation_and_reset_tokens_are_hashed_single_use_expiring_and_revocable(): void
    {
        $links = app(MemberLinks::class);
        $link = $links->issue('invite', 'new@example.test', null);
        self::assertNotSame($link['token'], DB::table('invitations')->value('token_hash'));
        $user = $links->consume('invite', $link['token'], 'twelve-long-pass', 'Neu');
        self::assertSame('new@example.test', $user->email);
        try {
            $links->consume('invite', $link['token'], 'another-long-pass', 'Neu');
            self::fail();
        } catch (ValidationException) {
        }
        $reset = $links->issue('reset', $user->email, $user->id);
        $this->travel(31)->minutes();
        try {
            $links->consume('reset', $reset['token'], 'another-long-pass', 'Neu');
            self::fail();
        } catch (ValidationException) {
        }
        $reset = $links->issue('reset', $user->email, $user->id);
        DB::table('password_reset_tokens')->where('id', $reset['id'])->update(['revoked_at' => now()]);
        try {
            $links->consume('reset', $reset['token'], 'another-long-pass', 'Neu');
            self::fail();
        } catch (ValidationException) {
        }
        $reset = $links->issue('reset', $user->email, $user->id);
        $links->consume('reset', $reset['token'], 'another-long-pass', 'Neu');
        self::assertTrue(Hash::check('another-long-pass', $user->fresh()->password));
    }

    public function test_last_member_cannot_be_removed(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(MemberLinks::class)->remove($user->id);
    }

    public function test_maintenance_blocks_web_api_and_background_and_only_local_pin_unlocks(): void
    {
        app(Settings::class)->set('maintenance', true);
        $this->get('/')->assertStatus(503)->assertSee('Wartungsmodus');
        $this->postJson('/api/v1/sync', [])->assertStatus(503);
        $this->post('/anmelden', ['pin' => '123456'])->assertStatus(503);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post('/wartung/entsperren', ['pin' => '123456'])->assertForbidden();
        self::assertTrue(app(Settings::class)->maintenance());
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('/wartung/entsperren', ['pin' => '123456'])->assertRedirect('/');
        self::assertFalse(app(Settings::class)->maintenance());
    }

    public function test_local_settings_reject_proxy_header_spoofing_and_boot_invalidates_pin_session(): void
    {
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => 'old-boot'])->get('/')->assertRedirect('/anmelden');
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()])->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->withHeader('X-Forwarded-For', '127.0.0.1')->get('/einstellungen/lokal')->assertForbidden();
    }

    public function test_sensitive_values_are_not_flashed_back_after_invalid_local_settings(): void
    {
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()])
            ->post('/einstellungen/lokal', ['pin' => '123456', 'smb_password' => 'private-smb-password', 'new_pin' => '654321'])
            ->assertSessionHasErrors()->assertSessionMissing('_old_input.pin')->assertSessionMissing('_old_input.smb_password')->assertSessionMissing('_old_input.new_pin');
    }
}
