<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessGuard;
use App\Domain\Access\MemberLinks;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AccessController
{
    public function form()
    {
        return view('auth.login');
    }

    public function login(Request $request, AccessGuard $guard)
    {
        if (config('privatebar.mode') === 'pi') {
            if (! $guard->pin((string) $request->input('pin'))) {
                throw ValidationException::withMessages(['pin' => 'Die PIN ist nicht korrekt.']);
            }
            $request->session()->regenerate();
            $request->session()->put(['kiosk_unlocked' => true, 'boot_id' => $guard->bootId()]);
        } else {
            $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
            $email = mb_strtolower($data['email']);
            $user = null;
            $ok = $guard->attempt('online:'.$email.':'.$request->ip(), 2, 30, function () use ($email, $data, &$user) {
                $user = User::query()->where('email', $email)->first();

                return Hash::check($data['password'], $user->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            });
            if (! $ok || ! $user) {
                throw ValidationException::withMessages(['access' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
            }
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/anmelden');
    }

    public function linkForm(string $type, string $token)
    {
        abort_unless(config('privatebar.mode') === 'cloud' && in_array($type, ['invite', 'reset']), 404);

        return view('auth.link', compact('type', 'token'));
    }

    public function redeem(Request $request, string $type, string $token, MemberLinks $links)
    {
        abort_unless(config('privatebar.mode') === 'cloud', 404);
        $data = $request->validate(['name' => ($type === 'invite' ? 'required' : 'nullable').'|string|max:100', 'password' => 'required|string|min:12|max:1024|confirmed']);
        $user = $links->consume($type, $token, $data['password'], $data['name'] ?? '');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    public function unlockMaintenance(Request $request, AccessGuard $guard, Settings $settings)
    {
        if (! $guard->pin((string) $request->input('pin'))) {
            throw ValidationException::withMessages(['pin' => 'Die PIN ist nicht korrekt.']);
        }
        $settings->set('maintenance', false);
        $request->session()->regenerate();
        $request->session()->put(['kiosk_unlocked' => true, 'boot_id' => $guard->bootId()]);

        return redirect('/');
    }
}
