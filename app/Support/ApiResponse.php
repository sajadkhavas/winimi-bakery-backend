<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => array_filter([
                'requestId' => self::requestId(),
                'apiVersion' => (string) config('winimi.api.version', '1'),
                ...$meta,
            ], static fn (mixed $value): bool => $value !== null),
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        int $status,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => array_filter([
                'requestId' => self::requestId(),
                'apiVersion' => (string) config('winimi.api.version', '1'),
                ...$meta,
            ], static fn (mixed $value): bool => $value !== null),
        ], $status);
    }

    private static function requestId(): ?string
    {
        return app()->bound('winimi.request_id')
            ? (string) app('winimi.request_id')
            : null;
    }
}
