<?php

namespace App\Http\Middleware;

use App\Services\Store\StorefrontRedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function __construct(
        private readonly StorefrontRedirectService $redirects,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $source = '/'.ltrim($request->path(), '/');
        $redirect = $this->redirects->resolve($source, incrementHit: true);

        if ($redirect !== null) {
            return redirect($redirect['location'], $redirect['statusCode']);
        }

        return $next($request);
    }
}
