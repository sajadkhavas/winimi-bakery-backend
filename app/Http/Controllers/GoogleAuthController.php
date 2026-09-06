<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAuthException;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        try {
            return redirect()->away($google->begin($request, 'login'));
        } catch (GoogleAuthException $exception) {
            return $this->frontendRedirect('login', 'error', $exception->reason);
        }
    }

    public function link(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        try {
            return redirect()->away($google->begin($request, 'link'));
        } catch (GoogleAuthException $exception) {
            return $this->frontendRedirect('link', 'error', $exception->reason);
        }
    }

    public function callback(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        try {
            $result = $google->consumeCallback($request);
            $customer = $result['customer'];
            $mode = $result['mode'];

            if ($mode === 'login') {
                Auth::guard('customer')->login($customer);
                $request->session()->regenerate();
            }

            return $this->frontendRedirect(
                $mode,
                $mode === 'link' ? 'linked' : 'success',
            );
        } catch (GoogleAuthException $exception) {
            return $this->frontendRedirect($exception->mode, 'error', $exception->reason);
        }
    }

    private function frontendRedirect(string $mode, string $status, ?string $reason = null): RedirectResponse
    {
        $frontend = rtrim((string) config('auth_features.google.frontend_url'), '/');
        $path = $mode === 'link' ? '/account' : '/account/login';
        $query = array_filter([
            'google' => $status,
            'code' => $reason,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return redirect()->away($frontend.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }
}
