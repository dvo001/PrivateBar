<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;

final class Maintenance
{
    public function handle(Request $request, Closure $next)
    {
        if (app(Settings::class)->maintenance() && ! $request->is('wartung/entsperren')) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'PrivateBar ist im Wartungsmodus.'], 503);
            }

            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
