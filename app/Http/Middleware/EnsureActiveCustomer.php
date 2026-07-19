<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if (! $customer || ! $customer->is_active) {
            Auth::guard('customer')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return ApiResponse::error('نشست کاربری معتبر نیست.', 401);
        }

        return $next($request);
    }
}
