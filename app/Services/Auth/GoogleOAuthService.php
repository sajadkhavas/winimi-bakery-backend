<?php

namespace App\Services\Auth;

use App\Exceptions\GoogleAuthException;
use App\Models\Customer;
use App\Models\CustomerOAuthIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GoogleOAuthService
{
    private const PROVIDER = 'google';

    private const SESSION_STATE = 'winimi.google_oauth.state';

    private const SESSION_MODE = 'winimi.google_oauth.mode';

    private const SESSION_LINK_CUSTOMER_ID = 'winimi.google_oauth.link_customer_id';

    public function isAvailable(): bool
    {
        return (bool) config('auth_features.google.enabled')
            && $this->configuredString('client_id') !== ''
            && $this->configuredString('client_secret') !== ''
            && $this->configuredString('redirect_uri') !== '';
    }

    public function begin(Request $request, string $mode = 'login'): string
    {
        if (! $this->isAvailable()) {
            throw new GoogleAuthException('google_unavailable', $mode, 'ورود با گوگل هنوز فعال نشده است.');
        }

        if (! in_array($mode, ['login', 'link'], true)) {
            throw new GoogleAuthException('invalid_mode', 'login');
        }

        $linkCustomerId = null;
        if ($mode === 'link') {
            $customer = $request->user('customer');
            if (! $customer instanceof Customer) {
                throw new GoogleAuthException('authentication_required', 'link');
            }
            $linkCustomerId = $customer->getKey();
        }

        $state = bin2hex(random_bytes(32));
        $request->session()->put(self::SESSION_STATE, $state);
        $request->session()->put(self::SESSION_MODE, $mode);

        if ($linkCustomerId !== null) {
            $request->session()->put(self::SESSION_LINK_CUSTOMER_ID, $linkCustomerId);
        } else {
            $request->session()->forget(self::SESSION_LINK_CUSTOMER_ID);
        }

        return $this->configuredString('authorization_url').'?'.http_build_query([
            'client_id' => $this->configuredString('client_id'),
            'redirect_uri' => $this->configuredString('redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{customer: Customer, mode: string}
     */
    public function consumeCallback(Request $request): array
    {
        $expectedState = (string) $request->session()->pull(self::SESSION_STATE, '');
        $mode = (string) $request->session()->pull(self::SESSION_MODE, 'login');
        $linkCustomerId = $request->session()->pull(self::SESSION_LINK_CUSTOMER_ID);
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw new GoogleAuthException('invalid_state', $mode, 'پاسخ ورود با گوگل معتبر نیست.');
        }

        if ($request->filled('error')) {
            $reason = $request->query('error') === 'access_denied'
                ? 'access_denied'
                : 'provider_error';

            throw new GoogleAuthException($reason, $mode, 'ورود با گوگل لغو شد یا کامل نشد.');
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            throw new GoogleAuthException('missing_code', $mode);
        }

        $profile = $this->fetchProfile($code, $mode);

        return DB::transaction(function () use ($profile, $mode, $linkCustomerId): array {
            $customer = $mode === 'link'
                ? $this->linkIdentity($profile, $linkCustomerId)
                : $this->loginOrCreate($profile);

            return ['customer' => $customer, 'mode' => $mode];
        });
    }

    /**
     * @return array{sub: string, email: string, name: ?string}
     */
    private function fetchProfile(string $code, string $mode): array
    {
        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeoutSeconds())
                ->post($this->configuredString('token_url'), [
                    'code' => $code,
                    'client_id' => $this->configuredString('client_id'),
                    'client_secret' => $this->configuredString('client_secret'),
                    'redirect_uri' => $this->configuredString('redirect_uri'),
                    'grant_type' => 'authorization_code',
                ]);

            if (! $tokenResponse->successful()) {
                throw new GoogleAuthException('token_exchange_failed', $mode);
            }

            $accessToken = trim((string) $tokenResponse->json('access_token', ''));
            if ($accessToken === '') {
                throw new GoogleAuthException('token_exchange_failed', $mode);
            }

            $profileResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout($this->timeoutSeconds())
                ->get($this->configuredString('userinfo_url'));

            if (! $profileResponse->successful()) {
                throw new GoogleAuthException('userinfo_failed', $mode);
            }
        } catch (GoogleAuthException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new GoogleAuthException('provider_unreachable', $mode);
        }

        $sub = trim((string) $profileResponse->json('sub', ''));
        $email = strtolower(trim((string) $profileResponse->json('email', '')));
        $emailVerified = filter_var(
            $profileResponse->json('email_verified', false),
            FILTER_VALIDATE_BOOL,
        );

        if (
            $sub === ''
            || strlen($sub) > 255
            || $email === ''
            || strlen($email) > 255
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! $emailVerified
        ) {
            throw new GoogleAuthException('unverified_identity', $mode, 'حساب گوگل ایمیل تأییدشده معتبری ارائه نکرد.');
        }

        $name = trim((string) $profileResponse->json('name', ''));

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => $name === '' ? null : mb_substr($name, 0, 120),
        ];
    }

    /**
     * @param array{sub: string, email: string, name: ?string} $profile
     */
    private function loginOrCreate(array $profile): Customer
    {
        $identity = CustomerOAuthIdentity::query()
            ->where('provider', self::PROVIDER)
            ->where('provider_user_id', $profile['sub'])
            ->first();

        if ($identity) {
            $customer = Customer::withTrashed()->find($identity->customer_id);
            if (! $customer || $customer->trashed() || ! $customer->is_active) {
                throw new GoogleAuthException('account_unavailable');
            }

            $identity->update([
                'email' => $profile['email'],
                'email_verified' => true,
            ]);
            $customer->forceFill(['last_login_at' => now()])->save();

            return $customer->fresh();
        }

        $existingEmailCustomer = Customer::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$profile['email']])
            ->first();

        if ($existingEmailCustomer) {
            throw new GoogleAuthException(
                'account_link_required',
                'login',
                'این ایمیل قبلاً در وینیمی استفاده شده است؛ برای امنیت، اتصال خودکار انجام نشد.',
            );
        }

        $customer = Customer::query()->create([
            'mobile' => null,
            'full_name' => $profile['name'],
            'email' => $profile['email'],
            'mobile_verified_at' => null,
            'last_login_at' => now(),
            'is_active' => true,
            'marketing_consent' => false,
        ]);

        CustomerOAuthIdentity::query()->create([
            'customer_id' => $customer->id,
            'provider' => self::PROVIDER,
            'provider_user_id' => $profile['sub'],
            'email' => $profile['email'],
            'email_verified' => true,
        ]);

        return $customer->fresh();
    }

    /**
     * @param array{sub: string, email: string, name: ?string} $profile
     */
    private function linkIdentity(array $profile, mixed $linkCustomerId): Customer
    {
        $guardCustomerId = Auth::guard('customer')->id();
        if (! is_numeric($linkCustomerId) || (int) $linkCustomerId !== (int) $guardCustomerId) {
            throw new GoogleAuthException('link_session_invalid', 'link');
        }

        $customer = Customer::query()->active()->find((int) $linkCustomerId);
        if (! $customer) {
            throw new GoogleAuthException('account_unavailable', 'link');
        }

        $claimedIdentity = CustomerOAuthIdentity::query()
            ->where('provider', self::PROVIDER)
            ->where('provider_user_id', $profile['sub'])
            ->first();

        if ($claimedIdentity && $claimedIdentity->customer_id !== $customer->id) {
            throw new GoogleAuthException('identity_taken', 'link');
        }

        $currentIdentity = CustomerOAuthIdentity::query()
            ->where('customer_id', $customer->id)
            ->where('provider', self::PROVIDER)
            ->first();

        if ($currentIdentity && $currentIdentity->provider_user_id !== $profile['sub']) {
            throw new GoogleAuthException('provider_already_linked', 'link');
        }

        CustomerOAuthIdentity::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'provider' => self::PROVIDER,
            ],
            [
                'provider_user_id' => $profile['sub'],
                'email' => $profile['email'],
                'email_verified' => true,
            ],
        );

        return $customer->fresh();
    }

    private function configuredString(string $key): string
    {
        return trim((string) config("auth_features.google.{$key}", ''));
    }

    private function timeoutSeconds(): int
    {
        return max(1, min(30, (int) config('auth_features.google.timeout_seconds', 10)));
    }
}
