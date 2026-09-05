<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class LocalOnly
{
    public function handle(Request $request, Closure $next)
    {
        // REMOTE_ADDR wird absichtlich ohne Forwarded-/Proxy-Header geprüft.
        abort_unless(config('privatebar.mode') === 'pi' && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true), 403, 'Nur direkt am Raspberry Pi verfügbar.');

        return $next($request);
    }
}
