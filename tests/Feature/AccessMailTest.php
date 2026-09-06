<?php

namespace Tests\Feature;

use App\Domain\Access\AccessMail;
use App\Domain\Access\MemberLinks;
use App\Domain\Settings\Settings;
use App\Mail\AccessMessage;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AccessMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatebar.mode' => 'cloud', 'mail.default' => 'smtp', 'app.url' => 'https://bar.example.test']);
        Mail::fake();
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ], absolute: false);
    }

    public function test_invitation_is_emailed_then_account_requires_separate_verification(): void
    {
        Event::fake([Verified::class]);
        $member = User::factory()->create();
        $response = $this->actingAs($member)->post('/einstellungen/mitglieder/link', ['type' => 'invite', 'email' => 'new@example.test']);
        $response->assertOk()->assertSee('Einladung versendet');
        $link = $response->viewData('link');
        self::assertStringStartsWith('https://bar.example.test/zugang/invite/', $link['url']);
        self::assertSame(hash('sha256', $link['token']), DB::table('invitations')->value('token_hash'));
        Mail::assertSent(AccessMessage::class, fn ($mail) => $mail->kind === 'invite' && $mail->hasTo('new@example.test') && $mail->accessUrl === $link['url']);
        $this->post('/zugang/invite/'.$link['token'], ['name' => 'Neu', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])->assertRedirect('/email-bestaetigen');
        $new = User::where('email', 'new@example.test')->firstOrFail();
        self::assertFalse($new->hasVerifiedEmail());
        $this->get('/')->assertRedirect('/email-bestaetigen');
        $this->post('/einstellungen/mitglieder/link', ['type' => 'invite', 'email' => 'blocked@example.test'])->assertRedirect('/email-bestaetigen');
        self::assertSame(1, DB::table('invitations')->count());
        $sent = Mail::sent(AccessMessage::class, fn ($mail) => $mail->kind === 'verify')->sole();
        self::assertTrue($sent->hasTo('new@example.test'));
        $this->get($sent->accessUrl)->assertRedirect('/');
        self::assertTrue($new->fresh()->hasVerifiedEmail());
        $verifiedAt = $new->fresh()->email_verified_at;
        $this->travel(1)->minutes();
        $this->get($sent->accessUrl)->assertRedirect('/');
        self::assertTrue($verifiedAt->eq($new->fresh()->email_verified_at));
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->get('/')->assertOk();
    }

    public function test_existing_unverified_member_receives_mail_after_login(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('a-long-test-password')]);
        $this->post('/anmelden', ['email' => $user->email, 'password' => 'a-long-test-password'])->assertRedirect('/email-bestaetigen');
        Mail::assertSent(AccessMessage::class, fn ($mail) => $mail->kind === 'verify' && $mail->hasTo($user->email));
        $this->get('/email-bestaetigen')->assertOk()->assertSee($user->email);
    }

    public function test_verification_mail_can_only_be_resent_once_per_minute(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/email-bestaetigen/senden')->assertRedirect('/email-bestaetigen');
        $this->post('/email-bestaetigen/senden')->assertSessionHas('message', 'Bitte warte eine Minute vor dem nächsten Versandversuch.');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/email-bestaetigen/senden');
        Mail::assertSentCount(2);
        $this->get($this->verificationUrl($user));
        $this->post('/email-bestaetigen/senden');
        Mail::assertSentCount(2);
    }

    public function test_expired_or_tampered_verification_links_do_not_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->actingAs($user)->get($url.'&extra=tampered')->assertForbidden();
        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
        self::assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_is_bound_to_the_logged_in_user_and_current_email(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->actingAs($other)->get($url)->assertForbidden();
        $user->update(['email' => 'changed@example.test']);
        $this->actingAs($user)->get($url)->assertForbidden();
        self::assertFalse($user->fresh()->hasVerifiedEmail());
        self::assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verification_opened_in_another_browser_survives_login_redirect(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('a-long-test-password')]);
        $url = $this->verificationUrl($user);
        $this->get($url)->assertRedirect('/anmelden');
        $this->post('/anmelden', ['email' => $user->email, 'password' => 'a-long-test-password'])->assertRedirect();
        $this->get($url)->assertRedirect('/');
        self::assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_invitation_mail_failure_revokes_link_and_does_not_expose_smtp_details(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp-secret-password'));
        $this->actingAs(User::factory()->create())->from('/einstellungen/mitglieder')
            ->post('/einstellungen/mitglieder/link', ['type' => 'invite', 'email' => 'new@example.test'])
            ->assertSessionHasErrors(['email' => AccessMail::FAILED.' Die Einladung wurde widerrufen; du kannst sie erneut erstellen.']);
        self::assertNotNull(DB::table('invitations')->value('revoked_at'));
    }

    public function test_log_mailer_never_receives_invitation_or_verification_tokens(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->unverified()->create();
        self::assertSame(AccessMail::FAILED, app(AccessMail::class)->verification($user));
        self::assertFalse(app(AccessMail::class)->invitation('new@example.test', 'https://bar.example.test/secret'));
        Mail::assertNothingSent();
    }

    public function test_verification_delivery_failure_keeps_account_unverified_and_allows_retry(): void
    {
        $user = User::factory()->unverified()->create();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('private-smtp-details'));
        $this->actingAs($user)->post('/email-bestaetigen/senden')->assertSessionHas('message', AccessMail::FAILED);
        self::assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->travel(61)->seconds();
        $this->post('/email-bestaetigen/senden');
        Mail::assertSentCount(1);
    }

    public function test_pi_and_maintenance_do_not_send_emails(): void
    {
        $user = User::factory()->unverified()->create();
        app(Settings::class)->set('maintenance', true);
        $this->actingAs($user)->post('/email-bestaetigen/senden')->assertStatus(503);
        app(Settings::class)->set('maintenance', false);
        config(['privatebar.mode' => 'pi']);
        $this->post('/email-bestaetigen/senden')->assertNotFound();
        self::assertFalse(app(AccessMail::class)->invitation($user->email, 'https://bar.example.test/invite'));
        Mail::assertNothingSent();
    }

    public function test_reset_links_remain_manual_and_do_not_bypass_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $link = app(MemberLinks::class)->issue('reset', $user->email, null);
        Mail::assertNothingSent();
        $this->post('/zugang/reset/'.$link['token'], ['password' => 'a-long-reset-password', 'password_confirmation' => 'a-long-reset-password'])->assertRedirect('/email-bestaetigen');
        self::assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertSent(AccessMessage::class, fn ($mail) => $mail->kind === 'verify');
    }

    public function test_email_templates_render_german_html_and_plain_text(): void
    {
        $mail = new AccessMessage('invite', 'https://bar.example.test/zugang/invite/token');
        $mail->assertSeeInHtml('Einladung annehmen');
        $mail->assertSeeInText('30 Minuten');
        $url = 'https://bar.example.test'.$this->verificationUrl(User::factory()->unverified()->create());
        $verification = new AccessMessage('verify', $url);
        $verification->assertSeeInHtml('E-Mail-Adresse bestätigen');
        $verification->assertSeeInText('Bestätige deine E-Mail-Adresse');
        $verification->assertSeeInText($url);
    }
}
