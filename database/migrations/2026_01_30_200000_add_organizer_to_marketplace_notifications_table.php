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

            // Add index for organizer notifications
            $table->index(['marketplace_organizer_id', 'read_at']);
            $table->index(['marketplace_organizer_id', 'type']);
            $table->index(['marketplace_organizer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_notifications', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropIndex(['marketplace_organizer_id', 'read_at']);
            $table->dropIndex(['marketplace_organizer_id', 'type']);
            $table->dropIndex(['marketplace_organizer_id', 'created_at']);
            $table->dropColumn('marketplace_organizer_id');
        });
    }
};
