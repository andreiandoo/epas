<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (! Schema::hasColumn('events', 'thank_you_message')) {
            Schema::table('events', function (Blueprint $table) {
                // Per-event WYSIWYG message shown on the order-confirmation
                // page after a successful purchase. Translatable JSON, same
                // pattern as `description` / `ticket_terms`. Nullable — when
                // absent the thank-you page skips the card entirely so
                // legacy events keep rendering unchanged.
                $table->json('thank_you_message')->nullable()->after('ticket_terms');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'thank_you_message')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('thank_you_message');
            });
        }
    }
};
