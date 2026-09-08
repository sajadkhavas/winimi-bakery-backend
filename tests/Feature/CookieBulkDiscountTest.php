<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Services\Orders\CookieBulkDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieBulkDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private BakeryProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'winimi.checkout.enabled' => true,
            'winimi.checkout.max_quantity_per_line' => 20,
            'winimi.checkout.max_total_units' => 50,
            'session.driver' => 'array',
        ]);

        $this->customer = Customer::query()->create([
            'mobile' => '09123456780',
            'full_name' => 'مشتری تست',
            'mobile_verified_at' => now(),
            'is_active' => true,
        ]);

        $category = BakeryCategory::query()->create([
            'name' => 'کوکی‌های خانگی',
            'slug' => 'kokyhay-khangy',
            'is_active' => true,
        ]);

        $product = BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'کوکی تست عمده',
            'slug' => 'bulk-cookie-test',
            'product_code' => 'BULK-COOKIE-001',
            'preparation_time_days' => 1,
            'availability_mode' => BakeryProduct::AVAILABILITY_STOCKED,
            'shipping_scope' => BakeryProduct::SHIPPING_NATIONWIDE,
            'content_verified' => true,
            'media_verified' => true,
            'requires_cooling' => false,
            'is_active' => true,
        ]);

        $this->variant = BakeryProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'name' => 'تک عددی',
            'sku' => 'BULK-COOKIE-001-EACH',
            'regular_price_toman' => 10_000,
            'sale_price_toman' => null,
            'packaging_fee_toman' => 0,
            'stock_quantity' => 500,
            'low_stock_threshold' => 10,
            'inventory_verified' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_cookie_bulk_discount_is_unreachable_below_threshold_and_applied_at_threshold(): void
    {
        $this->checkout('bulk-cookie-below-0001', 99)
            ->assertCreated()
            ->assertJsonPath('data.order.totals.subtotalToman', 990_000)
            ->assertJsonPath('data.order.totals.discountToman', 0)
            ->assertJsonPath('data.order.totals.grandTotalToman', 990_000);

        $this->checkout('bulk-cookie-at-threshold-0001', 100)
            ->assertCreated()
            ->assertJsonPath('data.order.totals.subtotalToman', 1_000_000)
            ->assertJsonPath('data.order.totals.discountToman', 100_000)
            ->assertJsonPath('data.order.totals.grandTotalToman', 900_000);

        $this->assertDatabaseHas('orders', [
            'subtotal_toman' => 1_000_000,
            'discount_total_toman' => 100_000,
            'grand_total_toman' => 900_000,
        ]);
    }

    public function test_admin_can_disable_bulk_discount_without_making_large_checkout_invalid(): void
    {
        StoreSetting::query()
            ->where('key', 'pricing.cookie_bulk_discount.enabled')
            ->update(['value' => '0']);

        config([
            'winimi.checkout.max_quantity_per_line' => 120,
            'winimi.checkout.max_total_units' => 120,
        ]);

        $this->checkout('bulk-cookie-disabled-0001', 100)
            ->assertCreated()
            ->assertJsonPath('data.order.totals.discountToman', 0)
            ->assertJsonPath('data.order.totals.grandTotalToman', 1_000_000);
    }

    public function test_bulk_discount_fails_closed_when_settings_are_missing(): void
    {
        StoreSetting::query()
            ->whereIn('key', [
                CookieBulkDiscountService::ENABLED_KEY,
                CookieBulkDiscountService::MIN_QUANTITY_KEY,
                CookieBulkDiscountService::PERCENT_KEY,
                CookieBulkDiscountService::CATEGORY_SLUGS_KEY,
            ])
            ->delete();

        $service = app(CookieBulkDiscountService::class);
        $configuration = $service->configuration();

        $this->assertFalse($configuration['enabled']);
        $this->assertSame([], $configuration['category_slugs']);
        $this->assertSame(1, $service->checkoutQuantityFloor());
    }

    public function test_malformed_checkout_item_returns_validation_error_instead_of_server_error(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->postJson('/api/checkout', [
                'customer' => [
                    'fullName' => 'مشتری تست',
                    'mobile' => '09123456780',
                    'province' => 'تهران',
                    'city' => 'تهران',
                    'address' => 'خیابان تست، پلاک ۱',
                    'postalCode' => '1234567890',
                    'notes' => null,
                ],
                'deliveryMethod' => 'standard',
                'items' => ['malformed'],
            ], [
                'Idempotency-Key' => 'malformed-checkout-item-0001',
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0']);
    }

    public function test_public_store_settings_expose_bulk_discount_contract(): void
    {
        $this->getJson('/api/store/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.pricing.cookie_bulk_discount.enabled', true)
            ->assertJsonPath('data.settings.pricing.cookie_bulk_discount.min_quantity', 100)
            ->assertJsonPath('data.settings.pricing.cookie_bulk_discount.percent', 10)
            ->assertJsonPath('data.settings.pricing.cookie_bulk_discount.category_slugs.0', 'kokyhay-khangy')
            ->assertJsonPath('data.settings.pricing.cookie_bulk_discount.category_slugs.1', 'myny-koky');
    }

    private function checkout(string $key, int $quantity)
    {
        return $this->actingAs($this->customer, 'customer')
            ->postJson('/api/checkout', [
                'customer' => [
                    'fullName' => 'مشتری تست',
                    'mobile' => '09123456780',
                    'province' => 'تهران',
                    'city' => 'تهران',
                    'address' => 'خیابان تست، پلاک ۱',
                    'postalCode' => '1234567890',
                    'notes' => null,
                ],
                'deliveryMethod' => 'standard',
                'items' => [[
                    'variantId' => $this->variant->public_id,
                    'quantity' => $quantity,
                ]],
            ], [
                'Idempotency-Key' => $key,
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ]);
    }
}
