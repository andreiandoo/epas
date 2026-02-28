<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('core_customers', 'marketplace_client_ids')) {
                $table->json('marketplace_client_ids')->nullable()->after('tenant_count');
            }
            if (!Schema::hasColumn('core_customers', 'primary_marketplace_client_id')) {
                $table->unsignedBigInteger('primary_marketplace_client_id')->nullable()->after('marketplace_client_ids');
                $table->foreign('primary_marketplace_client_id')
                    ->references('id')
                    ->on('marketplace_clients')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('core_customers', 'marketplace_client_count')) {
                $table->unsignedInteger('marketplace_client_count')->default(0)->after('primary_marketplace_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->dropForeign(['primary_marketplace_client_id']);
            $table->dropColumn(['marketplace_client_ids', 'primary_marketplace_client_id', 'marketplace_client_count']);
        });
    }
};
