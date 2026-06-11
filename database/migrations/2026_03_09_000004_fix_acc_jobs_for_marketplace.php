<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('acc_jobs')) {
            return;
        }

        // Add marketplace_client_id column
        if (!Schema::hasColumn('acc_jobs', 'marketplace_client_id')) {
            Schema::table('acc_jobs', function (Blueprint $table) {
                $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('id');
                $table->foreign('marketplace_client_id')
                    ->references('id')
                    ->on('marketplace_clients')
                    ->nullOnDelete();
            });
        }

        // Make tenant_id nullable string (was foreignId to tenants)
        if (Schema::hasColumn('acc_jobs', 'tenant_id')) {
            try {
                Schema::table('acc_jobs', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            } catch (\Exception $e) {
                // FK may not exist
            }

            // Drop composite indexes that include tenant_id
            try {
                Schema::table('acc_jobs', function (Blueprint $table) {
                    $table->dropIndex(['tenant_id', 'status']);
                });
            } catch (\Exception $e) {}

            try {
                Schema::table('acc_jobs', function (Blueprint $table) {
                    $table->dropIndex(['tenant_id', 'type']);
                });
            } catch (\Exception $e) {}

            Schema::table('acc_jobs', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->change();
            });

            // Re-add indexes
            Schema::table('acc_jobs', function (Blueprint $table) {
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'type']);
                $table->index('marketplace_client_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acc_jobs') && Schema::hasColumn('acc_jobs', 'marketplace_client_id')) {
            Schema::table('acc_jobs', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }
    }
};
