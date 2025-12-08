<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add renewal notification tracking columns to cookie_consents table.
     */
    public function up(): void
    {
        Schema::table('cookie_consents', function (Blueprint $table) {
            $table->timestamp('renewal_first_notified_at')->nullable()->after('withdrawn_at');
            $table->timestamp('renewal_reminder_notified_at')->nullable()->after('renewal_first_notified_at');

            $table->index('renewal_first_notified_at');
            $table->index('renewal_reminder_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cookie_consents', function (Blueprint $table) {
            $table->dropIndex(['renewal_first_notified_at']);
            $table->dropIndex(['renewal_reminder_notified_at']);
            $table->dropColumn(['renewal_first_notified_at', 'renewal_reminder_notified_at']);
        });
    }
};
