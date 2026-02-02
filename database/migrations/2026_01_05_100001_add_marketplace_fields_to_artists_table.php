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
        Schema::table('artists', function (Blueprint $table) {
            // Add marketplace association
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('id');
            $table->foreign('marketplace_client_id', 'artists_marketplace_fk')
                ->references('id')
                ->on('marketplace_clients')
                ->nullOnDelete();

            // Partner status
            $table->boolean('is_partner')->default(false)->after('is_active');
            $table->text('partner_notes')->nullable()->after('is_partner');

            // Featured status for displaying on homepage/dropdowns
            $table->boolean('is_featured')->default(false)->after('partner_notes');

            // Index for quick lookups
            $table->index(['marketplace_client_id', 'is_partner'], 'artists_mp_partner_idx');
            $table->index(['marketplace_client_id', 'is_featured'], 'artists_mp_featured_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropForeign('artists_marketplace_fk');
            $table->dropIndex('artists_mp_partner_idx');
            $table->dropIndex('artists_mp_featured_idx');
            $table->dropColumn(['marketplace_client_id', 'is_partner', 'partner_notes', 'is_featured']);
        });
    }
};
