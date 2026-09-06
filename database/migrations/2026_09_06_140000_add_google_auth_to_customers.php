<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('mobile', 11)->nullable()->change();
        });

        Schema::create('customer_oauth_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 255);
            $table->string('email')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['customer_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_oauth_identities');

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('mobile', 11)->nullable(false)->change();
            });
        }
    }
};
