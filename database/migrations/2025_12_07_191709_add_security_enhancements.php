<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Security enhancements:
     * - Add tenant_id to api_keys for proper tenant isolation
     * - Add key_hash for hashed API key storage
     */
    public function up(): void
    {
        // Add tenant_id to api_keys for proper tenant isolation
        Schema::table('api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('api_keys', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            // Add hashed key column for secure storage
            if (!Schema::hasColumn('api_keys', 'key_hash')) {
                $table->string('key_hash', 64)->nullable()->after('key');
                $table->index('key_hash');
            }
        });

        // Add security audit fields to track access
        Schema::table('api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('api_keys', 'last_used_ip')) {
                $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
            }

            if (!Schema::hasColumn('api_keys', 'total_requests')) {
                $table->bigInteger('total_requests')->default(0)->after('last_used_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('api_keys', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }

            if (Schema::hasColumn('api_keys', 'key_hash')) {
                $table->dropIndex(['key_hash']);
                $table->dropColumn('key_hash');
            }

            if (Schema::hasColumn('api_keys', 'last_used_ip')) {
                $table->dropColumn('last_used_ip');
            }

            if (Schema::hasColumn('api_keys', 'total_requests')) {
                $table->dropColumn('total_requests');
            }
        });
    }
};
