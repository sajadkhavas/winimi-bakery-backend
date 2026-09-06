<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\IranianMobile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $validated = $request->validate([
            'fullName' => ['sometimes', 'nullable', 'string', 'min:2', 'max:120'],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'marketingConsent' => ['sometimes', 'boolean'],
        ]);

        $customer->fill(array_filter([
            'full_name' => array_key_exists('fullName', $validated)
                ? $validated['fullName']
                : null,
            'email' => array_key_exists('email', $validated)
                ? $validated['email']
                : null,
            'marketing_consent' => array_key_exists('marketingConsent', $validated)
                ? $validated['marketingConsent']
                : null,
        ], static fn (mixed $value): bool => $value !== null));

        if (array_key_exists('fullName', $validated) && $validated['fullName'] === null) {
            $customer->full_name = null;
        }

        if (array_key_exists('email', $validated) && $validated['email'] === null) {
            $customer->email = null;
        }

        $customer->save();

        return ApiResponse::success([
            'user' => (new CustomerResource($customer->fresh()))->resolve($request),
        ], 'اطلاعات حساب به‌روزرسانی شد.');
    }

    public function completeMobile(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if ($customer->mobile !== null) {
            return ApiResponse::error(
                'شماره موبایل این حساب قبلاً ثبت شده است.',
                409,
                [],
                [],
                'mobile_already_set',
            );
        }

        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
        ]);

        try {
            $mobile = IranianMobile::normalize($validated['mobile']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'mobile' => [$exception->getMessage()],
            ]);
        }

        if (Customer::withTrashed()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => ['این شماره موبایل قبلاً استفاده شده است.'],
            ]);
        }

        try {
            $customer->forceFill([
                'mobile' => $mobile,
                'mobile_verified_at' => null,
            ])->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'mobile' => ['این شماره موبایل قبلاً استفاده شده است.'],
            ]);
        }

        return ApiResponse::success([
            'user' => (new CustomerResource($customer->fresh()))->resolve($request),
        ], 'شماره موبایل ثبت شد؛ تأیید آن پس از فعال‌شدن OTP انجام می‌شود.');
    }
}
