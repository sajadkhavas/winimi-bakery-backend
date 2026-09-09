<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_products', function (Blueprint $table): void {
            $table->json('taste_notes')->nullable()->after('description');
            $table->json('texture_notes')->nullable()->after('taste_notes');
            $table->json('use_cases')->nullable()->after('texture_notes');
            $table->text('serving_suggestions')->nullable()->after('use_cases');
            $table->json('specifications')->nullable()->after('serving_suggestions');
            $table->json('product_faqs')->nullable()->after('specifications');
            $table->string('content_version', 40)->nullable()->after('product_faqs');
            $table->timestamp('content_reviewed_at')->nullable()->after('content_version');
        });
    }

    public function down(): void
    {
        Schema::table('bakery_products', function (Blueprint $table): void {
            $table->dropColumn([
                'taste_notes',
                'texture_notes',
                'use_cases',
                'serving_suggestions',
                'specifications',
                'product_faqs',
                'content_version',
                'content_reviewed_at',
            ]);
        });
    }
};
