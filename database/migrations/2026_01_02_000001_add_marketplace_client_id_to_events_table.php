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
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->after('marketplace_client_id');

            $table->foreign('marketplace_client_id', 'events_mkt_client_fk')
                ->references('id')->on('marketplace_clients')->nullOnDelete();
            $table->foreign('marketplace_organizer_id', 'events_mkt_org_fk')
                ->references('id')->on('marketplace_organizers')->nullOnDelete();

            $table->index('marketplace_client_id');
            $table->index('marketplace_organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign('events_mkt_client_fk');
            $table->dropForeign('events_mkt_org_fk');
            $table->dropColumn(['marketplace_client_id', 'marketplace_organizer_id']);
        });
    }
};
