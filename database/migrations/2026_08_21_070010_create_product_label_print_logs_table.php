<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_label_print_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('label_template_id')->constrained('label_templates')->cascadeOnDelete();

            $table->uuid('printed_by_uuid')->nullable()->index();
            $table->integer('quantity_printed')->default(1);
            $table->dateTime('printed_at');

            $table->timestamps();

            $table->index(['business_uuid', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_label_print_logs');
    }
};
