<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DatabaseExportTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $verified = true): User
    {
        return User::forceCreate(['uuid' => (string) Str::uuid(), 'name' => 'Export test', 'email' => 'export@example.test',
            'email_verified_at' => $verified ? now() : null, 'password' => Hash::make('export-test-password')]);
    }

    public function test_export_requires_a_verified_cloud_member_and_current_password(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $this->post('/einstellungen/datenbank/export', ['password' => 'export-test-password'])->assertRedirect('/anmelden');
        $user = $this->member(false);
        $this->actingAs($user)->post('/einstellungen/datenbank/export', ['password' => 'export-test-password'])->assertRedirect('/email-bestaetigen');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->post('/einstellungen/datenbank/export', ['password' => 'incorrect-password'])->assertSessionHasErrors('password');
        self::assertNull(session()->getOldInput('password'));
        $this->post('/einstellungen/datenbank/export', [])->assertSessionHasErrors('password');
        $this->get('/einstellungen')->assertOk()->assertSee('Datenbankexport herunterladen');
    }

    public function test_pi_does_not_offer_an_export_even_with_a_personal_login(): void
    {
        config(['privatebar.mode' => 'pi']);
        $this->actingAs($this->member())->withSession(['kiosk_unlocked' => true,
            'boot_id' => app(AccessGuard::class)->bootId()]);
        $this->post('/einstellungen/datenbank/export', ['password' => 'export-test-password'])->assertForbidden();
        $this->get('/einstellungen')->assertOk()->assertDontSee('Datenbankexport herunterladen');
    }

    public function test_export_failure_returns_no_sql_or_credentials(): void
    {
        config(['privatebar.mode' => 'cloud']);
        $this->actingAs($this->member())->post('/einstellungen/datenbank/export', ['password' => 'export-test-password'])
            ->assertSessionHasErrors('export');
        self::assertNull(session()->getOldInput('password'));
        self::assertStringNotContainsString('export-test-password', (string) session('errors')->first('export'));
    }
}
