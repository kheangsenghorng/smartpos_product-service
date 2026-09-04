<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();
            $table->uuid('user_uuid')->nullable()->index();
            $table->string('name');
            $table->string('type'); // summary, full_catalog, pricing_sheet, inventory_audit, import_log
            $table->string('format')->default('csv'); // csv, json
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('total_records')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reports');
    }
};
