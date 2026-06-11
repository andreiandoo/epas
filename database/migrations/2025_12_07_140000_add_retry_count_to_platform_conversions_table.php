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
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('error_message');
        });

        // Add index for efficient querying of failed conversions to retry
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->index(['status', 'retry_count', 'updated_at'], 'platform_conversions_retry_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->dropIndex('platform_conversions_retry_index');
            $table->dropColumn('retry_count');
        });
    }
};
