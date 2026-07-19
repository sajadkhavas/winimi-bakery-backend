<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(30, max(1, (int) $request->integer('perPage', 10)));
        $orders = Order::query()
            ->ownedBy($request->user('customer'))
            ->with(['items', 'paymentAttempts'])
            ->latest('placed_at')
            ->paginate($perPage);

        return ApiResponse::success(
            OrderResource::collection($orders->getCollection())->resolve($request),
            meta: [
                'pagination' => [
                    'page' => $orders->currentPage(),
                    'perPage' => $orders->perPage(),
                    'total' => $orders->total(),
                    'totalPages' => $orders->lastPage(),
                ],
            ],
        );
    }

    public function show(Request $request, string $orderId): JsonResponse
    {
        $order = Order::query()
            ->ownedBy($request->user('customer'))
            ->where('public_id', $orderId)
            ->with(['items', 'reservations', 'paymentAttempts'])
            ->firstOrFail();

        return ApiResponse::success([
            'order' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function cancel(
        Request $request,
        string $orderId,
        OrderLifecycleService $lifecycle,
    ): JsonResponse {
        $order = Order::query()
            ->ownedBy($request->user('customer'))
            ->where('public_id', $orderId)
            ->firstOrFail();

        $cancelled = $lifecycle->cancelByCustomer($order, $request->user('customer'));

        return ApiResponse::success([
            'order' => (new OrderResource($cancelled))->resolve($request),
        ], 'سفارش لغو و رزرو موجودی آزاد شد.');
    }
}