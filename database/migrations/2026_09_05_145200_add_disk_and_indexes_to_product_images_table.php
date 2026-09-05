<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('disk', 50)
                ->default('public')
                ->after('image_path');

            $table->index(
                ['business_uuid', 'product_variant_id'],
                'product_images_business_variant_index'
            );

            $table->index(
                ['product_id', 'is_primary'],
                'product_images_product_primary_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_business_variant_index');
            $table->dropIndex('product_images_product_primary_index');

            $table->dropColumn('disk');
        });
    }
};
