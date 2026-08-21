<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('business_uuid')->index();

            $table->string('name', 100);
            $table->string('code', 20);
            $table->string('symbol', 20);

            $table->tinyInteger('precision')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->unique(['business_uuid', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
