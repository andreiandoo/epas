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
        Schema::table('marketplace_notifications', function (Blueprint $table) {
            $table->foreignId('marketplace_organizer_id')->nullable()->after('marketplace_client_id')->constrained()->cascadeOnDelete();

            // Add index for organizer notifications (short names for MySQL 64-char limit)
            $table->index(['marketplace_organizer_id', 'read_at'], 'mp_notif_organizer_read_idx');
            $table->index(['marketplace_organizer_id', 'type'], 'mp_notif_organizer_type_idx');
            $table->index(['marketplace_organizer_id', 'created_at'], 'mp_notif_organizer_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_notifications', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropIndex('mp_notif_organizer_read_idx');
            $table->dropIndex('mp_notif_organizer_type_idx');
            $table->dropIndex('mp_notif_organizer_created_idx');
            $table->dropColumn('marketplace_organizer_id');
        });
    }
};
