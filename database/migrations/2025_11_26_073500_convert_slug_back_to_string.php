<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Create temporary string column
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug_string', 190)->nullable()->after('slug');
        });

        // 2. Extract 'en' value from JSON slug and copy to slug_string
        $extract = match ($driver) {
            'pgsql'  => "CASE WHEN slug IS NULL THEN NULL WHEN slug::text = 'null' THEN NULL ELSE slug->>'en' END",
            default  => "CASE WHEN slug IS NULL THEN NULL WHEN slug = 'null' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(slug, '$.en')) END",
        };

        DB::statement("UPDATE events SET slug_string = {$extract}");

        // 3. Generate slug from title if slug_string is still null
        $extractTitle = match ($driver) {
            'pgsql'  => "CASE WHEN title IS NULL THEN NULL WHEN title::text = 'null' THEN NULL ELSE title->>'en' END",
            default  => "CASE WHEN title IS NULL THEN NULL WHEN title = 'null' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) END",
        };

        DB::statement("
            UPDATE events
            SET slug_string = CONCAT('event-', id)
            WHERE slug_string IS NULL OR slug_string = ''
        ");

        // 4. Drop old JSON slug column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        // 5. Rename slug_string to slug
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('slug_string', 'slug');
        });

        // 6. Make slug NOT NULL and add unique index
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug', 190)->nullable(false)->change();
        });

        // Add unique index if it doesn't exist
        if (!Schema::hasIndex('events', 'events_slug_unique')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        // Convert slug back to JSON
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->json('slug_json')->nullable()->after('slug');
        });

        $driver = DB::getDriverName();
        $makeJson = match ($driver) {
            'pgsql'  => "CASE WHEN slug IS NULL OR slug = '' THEN NULL ELSE jsonb_build_object('en', slug) END",
            default  => "CASE WHEN slug IS NULL OR slug = '' THEN NULL ELSE JSON_OBJECT('en', slug) END",
        };

        DB::statement("UPDATE events SET slug_json = {$makeJson}");

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slug');
            $table->renameColumn('slug_json', 'slug');
        });
    }
};
