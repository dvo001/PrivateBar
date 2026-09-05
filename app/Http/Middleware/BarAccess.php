<?php

namespace App\Http\Middleware;

use App\Domain\Access\AccessGuard;
use Closure;
use Illuminate\Http\Request;

final class BarAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (config('privatebar.mode') === 'cloud') {
            if (! auth()->guard()->check()) {
                return redirect()->guest(route('login'));
            }
        } elseif (! $request->session()->get('kiosk_unlocked') || $request->session()->get('boot_id') !== app(AccessGuard::class)->bootId()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
