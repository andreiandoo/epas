<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status-change events (resolved / closed / reopened / department moved /
 * assignee changed) need to show up in the conversation timeline so both
 * staff and organizer can see WHO did WHAT and WHEN. Cleanest place to put
 * them is the existing messages table — they sort with the rest of the
 * thread by created_at — but they need a flag so the Blade view can render
 * them as a slim system event line instead of a chat bubble.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->string('event_type', 32)->nullable()->after('body');
            $table->index('event_type', 'support_msg_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->dropIndex('support_msg_event_idx');
            $table->dropColumn('event_type');
        });
    }
};
