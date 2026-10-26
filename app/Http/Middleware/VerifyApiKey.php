<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('dtc.api_key');

        if ($expected === '') {
            abort(503, 'API key is not configured.');
        }

        if (! hash_equals($expected, (string) $request->header('X-DTC-Key'))) {
            abort(401, 'Invalid API key.');
        }

        return $next($request);
    }
}
