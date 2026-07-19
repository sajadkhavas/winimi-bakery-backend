<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'winimi-bakery-backend',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return ApiResponse::success([
                'status' => 'ready',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                ],
                'time' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                'سرویس هنوز آماده دریافت درخواست‌های وابسته به دیتابیس نیست.',
                503,
                [],
                ['checks' => ['application' => 'ok', 'database' => 'failed']],
            );
        }
    }

    public function meta(): JsonResponse
    {
        return ApiResponse::success([
            'service' => 'winimi-bakery-backend',
            'brand' => [
                'name' => config('winimi.brand.name'),
                'nameEn' => config('winimi.brand.name_en'),
            ],
            'apiVersion' => (string) config('winimi.api.version'),
            'contractVersion' => (string) config('winimi.api.contract_version'),
            'roadmapVersion' => (string) config('winimi.launch.roadmap_version'),
            'framework' => [
                'name' => 'Laravel',
                'version' => app()->version(),
            ],
            'legacyApiEnabled' => (bool) config('winimi.legacy.enabled'),
        ]);
    }

    public function contracts(): JsonResponse
    {
        return ApiResponse::success([
            'contractVersion' => (string) config('winimi.api.contract_version'),
            'contracts' => config('winimi.contracts', []),
            'launch' => config('winimi.launch', []),
            'notes' => [
                'مسیرهای /api/v1 متعلق به دامنه قدیمی ToolMaster هستند و موقتاً برای مهاجرت حفظ شده‌اند.',
                'بک‌اند پیش از اتصال نهایی فرانت باید به وضعیت backend_complete=ready برسد.',
                'پس از استقرار، فقط کد درگاه، کد اینماد و اطلاعات پنل پیامکی به‌عنوان ورودی خارجی باقی می‌مانند.',
            ],
        ]);
    }
}