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

        if (! Schema::hasColumn('events', 'video_url')) {
            Schema::table('events', function (Blueprint $table) {
                // YouTube URL (any format — watch, share, embed). Rendered
                // as an iframe on the public event page; admin + organizer
                // forms paste any youtube.com/youtu.be link.
                $table->string('video_url', 500)->nullable()->after('facebook_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'video_url')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('video_url');
            });
        }
    }
};
