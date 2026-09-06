<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessMail;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

final class VerificationController
{
    public function notice(Request $request)
    {
        abort_unless(config('privatebar.mode') === 'cloud', 404);
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/');
        }

        return view('auth.verify');
    }

    public function send(Request $request, AccessMail $mail)
    {
        abort_unless(config('privatebar.mode') === 'cloud', 404);
        /** @var User $user */
        $user = $request->user();

        return redirect()->route('verification.notice')->with('message', $mail->verification($user));
    }

    public function verify(EmailVerificationRequest $request)
    {
        abort_unless(config('privatebar.mode') === 'cloud', 404);
        $request->fulfill();

        return redirect('/')->with('message', 'Deine E-Mail-Adresse ist bestätigt. Willkommen in deiner Bar.');
    }
}
