<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('preparation_min_days')
                ->nullable()
                ->after('preparation_time_days');

            $table->unsignedSmallInteger('preparation_max_days')
                ->nullable()
                ->after('preparation_min_days');

            $table->string('availability_mode', 32)
                ->nullable()
                ->after('preparation_max_days');

            $table->string('shipping_scope', 32)
                ->nullable()
                ->after('requires_cooling');

            $table->text('shipping_note')
                ->nullable()
                ->after('shipping_scope');

            $table->index(
                'availability_mode',
                'bakery_products_availability_mode_index'
            );

            $table->index(
                'shipping_scope',
                'bakery_products_shipping_scope_index'
            );
        });

        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('package_quantity')
                ->nullable()
                ->after('weight_grams');

            $table->unsignedInteger('min_order_quantity')
                ->nullable()
                ->after('low_stock_threshold');

            $table->unsignedInteger('max_order_quantity')
                ->nullable()
                ->after('min_order_quantity');

            $table->boolean('inventory_verified')
                ->default(false)
                ->after('max_order_quantity');

            $table->index(
                'inventory_verified',
                'bakery_variants_inventory_verified_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->dropIndex('bakery_variants_inventory_verified_index');

            $table->dropColumn([
                'package_quantity',
                'min_order_quantity',
                'max_order_quantity',
                'inventory_verified',
            ]);
        });

        Schema::table('bakery_products', function (Blueprint $table): void {
            $table->dropIndex('bakery_products_availability_mode_index');
            $table->dropIndex('bakery_products_shipping_scope_index');

            $table->dropColumn([
                'preparation_min_days',
                'preparation_max_days',
                'availability_mode',
                'shipping_scope',
                'shipping_note',
            ]);
        });
    }
};
