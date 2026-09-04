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
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                if (! Schema::hasColumn('jobs', 'business_uuid')) {
                    $table->uuid('business_uuid')->nullable()->index()->after('queue');
                }
            });
        }

        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                if (! Schema::hasColumn('failed_jobs', 'business_uuid')) {
                    $table->uuid('business_uuid')->nullable()->index()->after('queue');
                }
                if (! Schema::hasColumn('failed_jobs', 'job_name')) {
                    $table->string('job_name')->nullable()->index()->after('business_uuid');
                }
                if (! Schema::hasColumn('failed_jobs', 'error_summary')) {
                    $table->string('error_summary', 500)->nullable()->after('exception');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                if (Schema::hasColumn('jobs', 'business_uuid')) {
                    $table->dropColumn(['business_uuid']);
                }
            });
        }

        if (Schema::hasTable('failed_jobs')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('failed_jobs', 'business_uuid')) {
                    $columns[] = 'business_uuid';
                }
                if (Schema::hasColumn('failed_jobs', 'job_name')) {
                    $columns[] = 'job_name';
                }
                if (Schema::hasColumn('failed_jobs', 'error_summary')) {
                    $columns[] = 'error_summary';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
