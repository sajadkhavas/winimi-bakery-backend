<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'bakery_media_assets',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('product_id')
                    ->nullable()
                    ->constrained('bakery_products')
                    ->nullOnDelete();

                $table->string('title', 220);

                $table->string('import_key', 190)
                    ->nullable()
                    ->unique();

                $table->string('source_filename', 255)
                    ->nullable();

                $table->char('source_sha256', 64)
                    ->nullable()
                    ->index();

                $table->unsignedSmallInteger(
                    'manifest_version'
                )
                    ->nullable()
                    ->index();

                $table->string('alt_text', 500)
                    ->nullable();

                $table->string('usage', 40)
                    ->default('unassigned')
                    ->index();

                $table->string('status', 40)
                    ->default('pending')
                    ->index();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'product_id',
                    'usage',
                    'status',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'bakery_media_assets'
        );
    }
};
