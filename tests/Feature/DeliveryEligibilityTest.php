<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use App\Models\Customer;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private BakeryProductVariant $dryVariant;

    private BakeryProductVariant $chilledVariant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'winimi.checkout.enabled' => true,
            'winimi.checkout.reservation_minutes' => 20,
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
            'name' => 'محصولات تست ارسال',
            'slug' => 'delivery-test-products',
            'is_active' => true,
        ]);

        $dryProduct = BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'محصول خشک تست',
            'slug' => 'delivery-dry-test',
            'product_code' => 'DELIVERY-DRY-001',
            'preparation_time_days' => 1,
            'availability_mode' => BakeryProduct::AVAILABILITY_STOCKED,
            'shipping_scope' => BakeryProduct::SHIPPING_NATIONWIDE,
            'content_verified' => true,
            'media_verified' => true,
            'requires_cooling' => false,
            'is_active' => true,
        ]);

        $chilledProduct = BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'محصول یخچالی تست',
            'slug' => 'delivery-chilled-test',
            'product_code' => 'DELIVERY-CHILLED-001',
            'preparation_time_days' => 1,
            'availability_mode' => BakeryProduct::AVAILABILITY_STOCKED,
            'shipping_scope' => BakeryProduct::SHIPPING_CONFIGURED_ZONES,
            'content_verified' => true,
            'media_verified' => true,
            'requires_cooling' => true,
            'is_active' => true,
        ]);

        $this->dryVariant = BakeryProductVariant::query()->create([
            'product_id' => $dryProduct->getKey(),
            'name' => 'خشک',
            'sku' => 'DELIVERY-DRY-V1',
            'regular_price_toman' => 100_000,
            'stock_quantity' => 20,
            'inventory_verified' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->chilledVariant = BakeryProductVariant::query()->create([
            'product_id' => $chilledProduct->getKey(),
            'name' => 'یخچالی',
            'sku' => 'DELIVERY-CHILLED-V1',
            'regular_price_toman' => 150_000,
            'stock_quantity' => 20,
            'inventory_verified' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_dry_cart_remains_nationwide_without_delivery_zone(): void
    {
        $this->checkout(
            'delivery-dry-nationwide-01',
            [['variantId' => $this->dryVariant->public_id, 'quantity' => 1]],
            'اصفهان',
            'اصفهان',
        )
            ->assertCreated()
            ->assertJsonPath('data.order.delivery.requiresCooling', false)
            ->assertJsonPath('data.order.delivery.method', 'standard')
            ->assertJsonPath('data.order.delivery.feeToman', 0);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_chilled_cart_is_rejected_when_destination_has_no_explicit_active_chilled_zone(): void
    {
        $this->checkout(
            'delivery-chilled-denied-01',
            [['variantId' => $this->chilledVariant->public_id, 'quantity' => 1]],
            'اصفهان',
            'اصفهان',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer.city');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    public function test_chilled_cart_is_accepted_for_exact_active_chilled_zone(): void
    {
        $this->createChilledZone('تهران', 'تهران');

        $this->checkout(
            'delivery-chilled-allowed-01',
            [['variantId' => $this->chilledVariant->public_id, 'quantity' => 1]],
            'تهران',
            'تهران',
        )
            ->assertCreated()
            ->assertJsonPath('data.order.delivery.requiresCooling', true)
            ->assertJsonPath('data.order.delivery.method', 'standard')
            ->assertJsonPath('data.order.delivery.feeToman', 0)
            ->assertJsonPath('data.order.delivery.feePayment', 'pay_on_delivery_to_courier')
            ->assertJsonPath('data.order.delivery.feeIncludedInOrder', false);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_inactive_or_non_chilled_zone_does_not_authorize_chilled_checkout(): void
    {
        DeliveryZone::query()->create([
            'name' => 'تهران بدون ارسال سرد',
            'province' => 'تهران',
            'city' => 'تهران',
            'standard_enabled' => true,
            'chilled_enabled' => false,
            'pickup_enabled' => false,
            'standard_fee_toman' => 0,
            'chilled_fee_toman' => 0,
            'pickup_fee_toman' => 0,
            'packaging_fee_toman' => 0,
            'preparation_min_days' => 0,
            'preparation_max_days' => 0,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->checkout(
            'delivery-chilled-disabled-01',
            [['variantId' => $this->chilledVariant->public_id, 'quantity' => 1]],
            'تهران',
            'تهران',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer.city');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_mixed_cart_is_treated_as_chilled_and_rejected_before_order_mutation(): void
    {
        $this->checkout(
            'delivery-mixed-denied-01',
            [
                ['variantId' => $this->dryVariant->public_id, 'quantity' => 1],
                ['variantId' => $this->chilledVariant->public_id, 'quantity' => 1],
            ],
            'فارس',
            'شیراز',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer.city');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
    }

    public function test_delivery_options_expose_destination_eligibility_without_changing_courier_fee_policy(): void
    {
        $this->getJson('/api/delivery/options?province=اصفهان&city=اصفهان&subtotalToman=100000&requiresCooling=0')
            ->assertOk()
            ->assertJsonPath('data.methods.0.method', 'standard')
            ->assertJsonPath('data.methods.0.enabled', true)
            ->assertJsonPath('data.methods.0.feeToman', 0)
            ->assertJsonPath('data.feePayment', 'pay_on_delivery_to_courier')
            ->assertJsonPath('data.feeIncludedInOrder', false);

        $this->getJson('/api/delivery/options?province=اصفهان&city=اصفهان&subtotalToman=150000&requiresCooling=1')
            ->assertOk()
            ->assertJsonPath('data.methods.0.enabled', false)
            ->assertJsonPath('data.methods.0.feeToman', 0);

        $this->createChilledZone('تهران', 'تهران');

        $this->getJson('/api/delivery/options?province=تهران&city=تهران&subtotalToman=150000&requiresCooling=1')
            ->assertOk()
            ->assertJsonPath('data.methods.0.enabled', true)
            ->assertJsonPath('data.methods.0.feeToman', 0);
    }

    public function test_chilled_zone_requires_explicit_city_and_cannot_authorize_an_entire_province(): void
    {
        DeliveryZone::query()->create([
            'name' => 'استان تهران - نباید wildcard باشد',
            'province' => 'تهران',
            'city' => null,
            'standard_enabled' => true,
            'chilled_enabled' => true,
            'pickup_enabled' => false,
            'standard_fee_toman' => 0,
            'chilled_fee_toman' => 0,
            'pickup_fee_toman' => 0,
            'packaging_fee_toman' => 0,
            'preparation_min_days' => 0,
            'preparation_max_days' => 0,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->checkout(
            'delivery-no-province-wildcard-01',
            [['variantId' => $this->chilledVariant->public_id, 'quantity' => 1]],
            'تهران',
            'شهر تأییدنشده',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer.city');

        $this->assertDatabaseCount('orders', 0);
    }

    private function checkout(
        string $idempotencyKey,
        array $items,
        string $province,
        string $city,
    ) {
        return $this->actingAs($this->customer, 'customer')
            ->postJson('/api/checkout', [
                'customer' => [
                    'fullName' => 'مشتری تست',
                    'mobile' => '09123456780',
                    'province' => $province,
                    'city' => $city,
                    'address' => 'نشانی تست، پلاک ۱',
                    'postalCode' => '1234567890',
                    'notes' => null,
                ],
                'deliveryMethod' => 'standard',
                'items' => $items,
            ], [
                'Idempotency-Key' => $idempotencyKey,
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/',
            ]);
    }

    private function createChilledZone(string $province, string $city): DeliveryZone
    {
        return DeliveryZone::query()->create([
            'name' => "پوشش سرد {$city}",
            'province' => $province,
            'city' => $city,
            'standard_enabled' => true,
            'chilled_enabled' => true,
            'pickup_enabled' => false,
            'standard_fee_toman' => 0,
            'chilled_fee_toman' => 0,
            'pickup_fee_toman' => 0,
            'packaging_fee_toman' => 0,
            'preparation_min_days' => 0,
            'preparation_max_days' => 0,
            'priority' => 1,
            'is_active' => true,
        ]);
    }
}
