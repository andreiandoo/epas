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
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'marketplace_client_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('marketplace_clients')
                    ->nullOnDelete();

                $table->index('marketplace_client_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'marketplace_client_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }
    }
};
