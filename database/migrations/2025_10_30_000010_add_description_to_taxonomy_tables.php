<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // event_categories
        if (! Schema::hasColumn('event_categories', 'description')) {
            Schema::table('event_categories', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        // event_genres
        if (! Schema::hasColumn('event_genres', 'description')) {
            Schema::table('event_genres', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        // music_genres
        if (! Schema::hasColumn('music_genres', 'description')) {
            Schema::table('music_genres', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        // event_tags
        if (! Schema::hasColumn('event_tags', 'description')) {
            Schema::table('event_tags', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        // event_categories
        if (Schema::hasColumn('event_categories', 'description')) {
            Schema::table('event_categories', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        // event_genres
        if (Schema::hasColumn('event_genres', 'description')) {
            Schema::table('event_genres', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        // music_genres
        if (Schema::hasColumn('music_genres', 'description')) {
            Schema::table('music_genres', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        // event_tags
        if (Schema::hasColumn('event_tags', 'description')) {
            Schema::table('event_tags', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
