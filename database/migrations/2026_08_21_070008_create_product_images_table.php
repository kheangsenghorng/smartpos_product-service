<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->string('image_path', 500);
            $table->string('alt_text', 255)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['business_uuid', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
