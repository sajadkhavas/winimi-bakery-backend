<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Store\StorefrontRedirectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorefrontRedirectController extends Controller
{
    public function __invoke(
        Request $request,
        StorefrontRedirectService $redirects,
    ): JsonResponse {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        $redirect = $redirects->resolve(
            (string) $validated['path'],
            incrementHit: true,
        );

        return ApiResponse::success([
            'found' => $redirect !== null,
            'location' => $redirect['location'] ?? null,
            'statusCode' => $redirect['statusCode'] ?? null,
        ]);
    }
}
