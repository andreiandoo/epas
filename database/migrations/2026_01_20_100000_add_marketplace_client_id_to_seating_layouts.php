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
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('marketplace_clients')
                ->onDelete('set null');

            $table->boolean('is_partner')->default(false)->after('marketplace_client_id');
            $table->text('partner_notes')->nullable()->after('is_partner');

            $table->index('marketplace_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn(['marketplace_client_id', 'is_partner', 'partner_notes']);
        });
    }
};
