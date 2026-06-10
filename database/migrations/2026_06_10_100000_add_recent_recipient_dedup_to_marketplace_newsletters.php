<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two columns on marketplace_newsletters that opt this campaign into a
     * "skip recipients who already received another newsletter in the last N
     * hours" filter at recipient-build time. Default = off + 48h, which keeps
     * every existing newsletter unchanged when the column is read back.
     */
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            $table->boolean('exclude_recent_recipients')
                ->default(false)
                ->after('target_artist_ids');

            $table->unsignedSmallInteger('recent_recipient_window_hours')
                ->default(48)
                ->after('exclude_recent_recipients');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            $table->dropColumn(['exclude_recent_recipients', 'recent_recipient_window_hours']);
        });
    }
};
