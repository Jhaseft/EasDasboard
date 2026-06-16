<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    /**
     * Validate the bot runner API key sent via the X-API-Key header
     * (or "Authorization: Bearer <key>").
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.bot_api.key');

        if (empty($expected)) {
            abort(503, 'Bot API key is not configured on the server.');
        }

        $provided = $request->header('X-API-Key') ?: $request->bearerToken();

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid or missing API key.');
        }

        return $next($request);
    }
}
