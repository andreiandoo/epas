<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Citit per operator. read_at de pe marketplace_notifications rămâne al organizatorului.
    public function up(): void
    {
        if (Schema::hasTable('marketplace_notification_reads')) {
            return;
        }

        Schema::create('marketplace_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_notification_id')->constrained('marketplace_notifications')->cascadeOnDelete();
            $table->foreignId('marketplace_admin_id')->constrained('marketplace_admins')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['marketplace_notification_id', 'marketplace_admin_id'], 'mp_notif_reads_unique');
            $table->index('marketplace_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_notification_reads');
    }
};
