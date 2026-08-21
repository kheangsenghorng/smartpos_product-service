<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->enum('code_type', ['barcode', 'qrcode']);
            $table->string('symbology', 30)->nullable();
            $table->string('code_value', 255);

            $table->string('image_path', 500)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_auto_generated')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['business_uuid', 'code_value']);
            $table->index(['business_uuid', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_codes');
    }
};
