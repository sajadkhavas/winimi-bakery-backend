<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('packaging_fee_toman')
                ->default(0)
                ->after('sale_price_toman');
        });
    }

    public function down(): void
    {
        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->dropColumn('packaging_fee_toman');
        });
    }
};
