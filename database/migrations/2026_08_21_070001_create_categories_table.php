<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('name', 150);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_uuid', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
