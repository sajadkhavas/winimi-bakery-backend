<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkLegacyApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('winimi.legacy.enabled', true)) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('X-Winimi-Legacy-Domain', 'toolmaster');
        $response->headers->set(
            'Link',
            sprintf('<%s>; rel="deprecation"', config('winimi.legacy.contract_url', '/api/system/contracts')),
        );

        return $response;
    }
}
