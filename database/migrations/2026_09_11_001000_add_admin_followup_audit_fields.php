<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_admin_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->index();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['customer_id', 'created_at'], 'customer_admin_actions_customer_time_index');
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('internal_note')->nullable()->after('assigned_user_id');
            $table->timestamp('last_action_at')->nullable()->after('internal_note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['internal_note', 'last_action_at']);
        });

        Schema::dropIfExists('customer_admin_actions');
    }
};
