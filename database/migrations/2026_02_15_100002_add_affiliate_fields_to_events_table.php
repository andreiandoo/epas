<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_affiliate')->default(false)->after('is_published');
            $table->foreignId('affiliate_event_source_id')->nullable()->after('is_affiliate')
                ->constrained('affiliate_event_sources')->nullOnDelete();
            $table->string('affiliate_url')->nullable()->after('affiliate_event_source_id');
            $table->json('affiliate_data')->nullable()->after('affiliate_url');
        });

        // Index for filtering affiliate vs regular events
        Schema::table('events', function (Blueprint $table) {
            $table->index(['marketplace_client_id', 'is_affiliate']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['marketplace_client_id', 'is_affiliate']);
            $table->dropConstrainedForeignId('affiliate_event_source_id');
            $table->dropColumn(['is_affiliate', 'affiliate_url', 'affiliate_data']);
        });
    }
};
