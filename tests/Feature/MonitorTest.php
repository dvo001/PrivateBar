<?php

namespace Tests\Feature;

use App\Domain\Access\AccessGuard;
use App\Domain\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_configuration_is_local_and_requires_kiosk_access(): void
    {
        config(['privatebar.mode' => 'pi']);
        $this->getJson('/monitor/anzeige')->assertRedirect('/anmelden');
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId()])->getJson('/monitor/anzeige')
            ->assertOk()->assertJson(['enabled' => false, 'minutes' => 29, 'style' => 'digital', 'brightness' => 30]);
        app(Settings::class)->set('monitor_clock_style', 'analog');
        app(Settings::class)->set('monitor_wake_minutes', 10);
        $this->getJson('/monitor/anzeige')->assertOk()->assertJson(['style' => 'analog', 'minutes' => 10]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->getJson('/monitor/anzeige')->assertForbidden();
    }

    public function test_background_poll_does_not_replace_previous_page(): void
    {
        config(['privatebar.mode' => 'pi']);
        $this->withSession(['kiosk_unlocked' => true, 'boot_id' => app(AccessGuard::class)->bootId(),
            '_previous' => ['url' => 'http://localhost/einstellungen']])
            ->withHeader('X-Requested-With', 'XMLHttpRequest')->getJson('/monitor/anzeige')
            ->assertOk()->assertSessionHas('_previous.url', 'http://localhost/einstellungen');
    }
}
