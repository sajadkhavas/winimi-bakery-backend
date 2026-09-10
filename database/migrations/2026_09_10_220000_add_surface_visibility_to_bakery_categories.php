<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_categories', function (Blueprint $table): void {
            $table->boolean('show_on_home')->default(true)->after('is_active');
            $table->boolean('show_in_footer')->default(true)->after('show_on_home');
            $table->unsignedSmallInteger('home_sort_order')->nullable()->after('show_in_footer');
            $table->unsignedSmallInteger('footer_sort_order')->nullable()->after('home_sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('bakery_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'show_on_home',
                'show_in_footer',
                'home_sort_order',
                'footer_sort_order',
            ]);
        });
    }
};
