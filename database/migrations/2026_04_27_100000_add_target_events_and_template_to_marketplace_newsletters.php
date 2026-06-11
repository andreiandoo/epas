<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            // Newsletter can target ticket buyers of specific events directly
            // (in addition to contact lists / tags). Stored as JSON array of
            // event IDs (epas events table). Recipients are dedup'ed by email
            // before sending.
            if (!Schema::hasColumn('marketplace_newsletters', 'target_event_ids')) {
                $table->json('target_event_ids')->nullable()->after('target_tags');
            }
            // Original email template id used when authoring; lets the editor
            // know "this draft was forked from template #N" so we can show it
            // in the UI without overriding subsequent edits to body_sections.
            if (!Schema::hasColumn('marketplace_newsletters', 'source_email_template_id')) {
                $table->unsignedBigInteger('source_email_template_id')->nullable()->after('target_event_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_newsletters', 'target_event_ids')) {
                $table->dropColumn('target_event_ids');
            }
            if (Schema::hasColumn('marketplace_newsletters', 'source_email_template_id')) {
                $table->dropColumn('source_email_template_id');
            }
        });
    }
};
