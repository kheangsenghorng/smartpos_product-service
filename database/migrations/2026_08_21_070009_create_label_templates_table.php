<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->string('name', 100);

            $table->decimal('width_mm', 8, 2);
            $table->decimal('height_mm', 8, 2);

            $table->boolean('show_product_name')->default(true);
            $table->boolean('show_variant_name')->default(true);
            $table->boolean('show_price')->default(true);
            $table->boolean('show_sku')->default(true);

            $table->boolean('show_barcode')->default(true);
            $table->boolean('show_qrcode')->default(false);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
