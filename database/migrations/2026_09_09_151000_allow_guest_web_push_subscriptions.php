<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_push_subscriptions', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->change();
            $table->char('guest_token_hash', 64)->nullable()->after('customer_id')->index();
        });
    }

    public function down(): void
    {
        DB::table('web_push_subscriptions')->whereNull('customer_id')->delete();
        Schema::table('web_push_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['guest_token_hash']);
            $table->dropColumn('guest_token_hash');
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};
