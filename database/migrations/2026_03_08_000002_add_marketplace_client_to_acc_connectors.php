<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acc_connectors') && !Schema::hasColumn('acc_connectors', 'marketplace_client_id')) {
            Schema::table('acc_connectors', function (Blueprint $table) {
                $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id');
                $table->foreign('marketplace_client_id')
                    ->references('id')
                    ->on('marketplace_clients')
                    ->nullOnDelete();
            });

            // Make tenant_id nullable (for marketplace-only connectors)
            if (Schema::hasColumn('acc_connectors', 'tenant_id')) {
                Schema::table('acc_connectors', function (Blueprint $table) {
                    $table->string('tenant_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acc_connectors') && Schema::hasColumn('acc_connectors', 'marketplace_client_id')) {
            Schema::table('acc_connectors', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }
    }
};
