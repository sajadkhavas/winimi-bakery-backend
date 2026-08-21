<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Store\DeliveryConfigurationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function options(
        Request $request,
        DeliveryConfigurationService $delivery,
    ): JsonResponse {
        $validated = $request->validate([
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'subtotalToman' => ['nullable', 'integer', 'min:0'],
            'requiresCooling' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success([
            'zone' => null,
            'feePayment' => DeliveryConfigurationService::FEE_PAYMENT_MODE,
            'feeIncludedInOrder' => false,
            'notice' => DeliveryConfigurationService::CUSTOMER_NOTICE,
            'methods' => $delivery->options(
                $validated['province'] ?? null,
                $validated['city'] ?? null,
                (int) ($validated['subtotalToman'] ?? 0),
                (bool) ($validated['requiresCooling'] ?? false),
            ),
        ]);
    }
}
