<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            $table->string('name', 255);
            $table->string('sku', 100);
            $table->string('slug', 255)->nullable();
            $table->text('description')->nullable();

            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true)->index();

            $table->date('available_from')->nullable()->index();
            $table->date('available_until')->nullable()->index();

            $table->uuid('created_by_uuid')->nullable();
            $table->uuid('updated_by_uuid')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_uuid', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
