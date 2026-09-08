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

        $this->variant = $this->createVariant(
            $category,
            'کوکی تست عمده',
            'bulk-cookie-test',
            'BULK-COOKIE-001',
        );
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

    public function test_bulk_cookie_checkout_can_exceed_the_discount_threshold(): void
    {
        $this->checkout('bulk-cookie-above-threshold-0001', 101)
            ->assertCreated()
            ->assertJsonPath('data.order.totals.subtotalToman', 1_010_000)
            ->assertJsonPath('data.order.totals.discountToman', 101_000)
            ->assertJsonPath('data.order.totals.grandTotalToman', 909_000);
    }

    public function test_admin_can_disable_bulk_discount_without_disabling_large_cookie_orders(): void
    {
        StoreSetting::query()
            ->where('key', CookieBulkDiscountService::ENABLED_KEY)
            ->update(['value' => '0']);

        $this->checkout('bulk-cookie-disabled-0001', 100)
            ->assertCreated()
            ->assertJsonPath('data.order.totals.discountToman', 0)
            ->assertJsonPath('data.order.totals.grandTotalToman', 1_000_000);
    }

    public function test_non_bulk_categories_keep_the_normal_per_line_limit(): void
    {
        $category = BakeryCategory::query()->create([
            'name' => 'کیک و دسر',
            'slug' => 'cake-dessert-test',
            'is_active' => true,
        ]);
        $standardVariant = $this->createVariant(
            $category,
            'کیک تست',
            'standard-cake-test',
            'STANDARD-CAKE-001',
        );

        $this->checkoutItems('standard-over-line-0001', [[
            'variantId' => $standardVariant->public_id,
            'quantity' => 21,
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_bulk_cookie_units_do_not_raise_limits_for_other_categories(): void
    {
        $category = BakeryCategory::query()->create([
            'name' => 'کیک و دسر',
            'slug' => 'cake-dessert-mixed-test',
            'is_active' => true,
        ]);
        $standardVariant = $this->createVariant(
            $category,
            'کیک تست ترکیبی',
            'mixed-cake-test',
            'MIXED-CAKE-001',
        );

        $this->checkoutItems('mixed-over-line-0001', [
            [
                'variantId' => $this->variant->public_id,
                'quantity' => 100,
            ],
            [
                'variantId' => $standardVariant->public_id,
                'quantity' => 21,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
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
        return $this->checkoutItems($key, [[
            'variantId' => $this->variant->public_id,
            'quantity' => $quantity,
        ]]);
    }

    /** @param array<int, array{variantId: string, quantity: int}> $items */
    private function checkoutItems(string $key, array $items)
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
                'items' => $items,
            ], [
                'Idempotency-Key' => $key,
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ]);
    }

    private function createVariant(
        BakeryCategory $category,
        string $name,
        string $slug,
        string $productCode,
    ): BakeryProductVariant {
        $product = BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => $name,
            'slug' => $slug,
            'product_code' => $productCode,
            'preparation_time_days' => 1,
            'availability_mode' => BakeryProduct::AVAILABILITY_STOCKED,
            'shipping_scope' => BakeryProduct::SHIPPING_NATIONWIDE,
            'content_verified' => true,
            'media_verified' => true,
            'requires_cooling' => false,
            'is_active' => true,
        ]);

        return BakeryProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'name' => 'تک عددی',
            'sku' => $productCode.'-EACH',
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
}
