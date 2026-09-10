<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('weight_min_grams')->nullable()->after('weight_grams');
            $table->unsignedInteger('weight_max_grams')->nullable()->after('weight_min_grams');
        });
    }

    public function down(): void
    {
        Schema::table('bakery_product_variants', function (Blueprint $table): void {
            $table->dropColumn(['weight_min_grams', 'weight_max_grams']);
        });
    }
};
