<?php

namespace AppRadar\Agent\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppRadarStatusToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('appradar.status_token', ''));

        if ($expected === '') {
            return $next($request);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-AppRadar-Token');

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
