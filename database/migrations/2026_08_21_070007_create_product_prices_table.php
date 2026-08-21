<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->char('currency_code', 3)->default('USD');

            $table->decimal('selling_price', 15, 4);
            $table->decimal('cost_price', 15, 4)->nullable();
            $table->decimal('minimum_price', 15, 4)->nullable();

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['business_uuid', 'product_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
