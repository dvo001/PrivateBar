<?php

namespace App\Domain\Access;

use App\Mail\AccessMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class AccessMail
{
    public const FAILED = 'Die E-Mail konnte nicht versendet werden. Bitte später erneut versuchen. Die SMTP-Konfiguration auf Cyon muss geprüft werden.';

    public function invitation(string $email, string $url): bool
    {
        return $this->send($email, new AccessMessage('invite', $url));
    }

    public function verification(User $user): string
    {
        if ($user->hasVerifiedEmail()) {
            return 'Deine E-Mail-Adresse ist bereits bestätigt.';
        }
        // Shared across login, invitation acceptance and the resend form.
        if (! Cache::add('verification-mail:'.$user->id, true, 60)) {
            return 'Bitte warte eine Minute vor dem nächsten Versandversuch.';
        }
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ], absolute: false);
        if (! $this->send($user->email, new AccessMessage('verify', rtrim(config('app.url'), '/').$path))) {
            return self::FAILED;
        }

        return 'Die Bestätigungs-E-Mail wurde an den Mailserver übergeben. Prüfe dein Postfach und den Spamordner. Der Link gilt 30 Minuten.';
    }

    private function send(string $email, AccessMessage $message): bool
    {
        // Never write credential-bearing links to Laravel's log/array mailers.
        if (config('privatebar.mode') !== 'cloud' || config('mail.default') !== 'smtp'
            || ! str_starts_with(config('app.url'), 'https://')) {
            return false;
        }
        try {
            Mail::to($email)->send($message);

            return true;
        } catch (\Throwable) {
            // SMTP exceptions may include credentials or message contents.
            return false;
        }
    }
}
