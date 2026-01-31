<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add new JSON columns (temporary *_trans)
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'title_trans')) {
                $table->json('title_trans')->nullable();
            }
            if (! Schema::hasColumn('events', 'subtitle_trans')) {
                $table->json('subtitle_trans')->nullable();
            }
            if (! Schema::hasColumn('events', 'short_description_trans')) {
                $table->json('short_description_trans')->nullable();
            }
            if (! Schema::hasColumn('events', 'description_trans')) {
                $table->json('description_trans')->nullable();
            }
            if (! Schema::hasColumn('events', 'slug_trans')) {
                $table->json('slug_trans')->nullable();
            }
        });

        // 2) Backfill: move old scalar values into JSON under 'en'
        // We support MySQL/SQLite (JSON_OBJECT) and PostgreSQL (jsonb_build_object)
        $driver = DB::getDriverName();

        $makeJson = fn(string $col) => match ($driver) {
            'pgsql'  => "CASE WHEN {$col} IS NULL OR {$col} = '' THEN NULL ELSE jsonb_build_object('en', {$col}) END",
            default  => "CASE WHEN {$col} IS NULL OR {$col} = '' THEN NULL ELSE JSON_OBJECT('en', {$col}) END",
        };

        $updates = [];
        if (Schema::hasColumn('events', 'title'))             $updates[] = "title_trans = {$makeJson('title')}";
        if (Schema::hasColumn('events', 'subtitle'))          $updates[] = "subtitle_trans = {$makeJson('subtitle')}";
        if (Schema::hasColumn('events', 'short_description')) $updates[] = "short_description_trans = {$makeJson('short_description')}";
        if (Schema::hasColumn('events', 'description'))       $updates[] = "description_trans = {$makeJson('description')}";
        if (Schema::hasColumn('events', 'slug'))              $updates[] = "slug_trans = {$makeJson('slug')}";

        if (! empty($updates)) {
            $sql = "UPDATE events SET " . implode(", ", $updates);
            DB::statement($sql);
        }

        // 3) Drop old columns if they exist
        Schema::table('events', function (Blueprint $table) {
            foreach (['title', 'subtitle', 'short_description', 'description', 'slug'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 4) Rename *_trans -> original names
        Schema::table('events', function (Blueprint $table) {
            $map = [
                'title_trans'             => 'title',
                'subtitle_trans'          => 'subtitle',
                'short_description_trans' => 'short_description',
                'description_trans'       => 'description',
                'slug_trans'              => 'slug',
            ];

            foreach ($map as $from => $to) {
                if (Schema::hasColumn('events', $from) && ! Schema::hasColumn('events', $to)) {
                    $table->renameColumn($from, $to);
                }
            }
        });

        // 5) (Optional) On PostgreSQL, ensure jsonb type
        if ($driver === 'pgsql') {
            foreach (['title','subtitle','short_description','description','slug'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    DB::statement("ALTER TABLE events ALTER COLUMN {$col} TYPE jsonb USING {$col}::jsonb");
                }
            }
        }
    }

    public function down(): void
    {
        // Recreate old scalar columns as text and copy back 'en'
        Schema::table('events', function (Blueprint $table) {
            foreach (['title_old','subtitle_old','short_description_old','description_old','slug_old'] as $col) {
                if (! Schema::hasColumn('events', $col)) {
                    if ($col === 'description_old') {
                        $table->longText($col)->nullable();
                    } else {
                        $table->text($col)->nullable();
                    }
                }
            }
        });

        $driver = DB::getDriverName();

        $extract = fn(string $col) => match ($driver) {
            'pgsql'  => "CASE WHEN {$col} IS NULL THEN NULL ELSE {$col}->>'en' END",
            default  => "CASE WHEN {$col} IS NULL THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT({$col}, '$.en')) END",
        };

        $updates = [];
        if (Schema::hasColumn('events', 'title'))             $updates[] = "title_old = {$extract('title')}";
        if (Schema::hasColumn('events', 'subtitle'))          $updates[] = "subtitle_old = {$extract('subtitle')}";
        if (Schema::hasColumn('events', 'short_description')) $updates[] = "short_description_old = {$extract('short_description')}";
        if (Schema::hasColumn('events', 'description'))       $updates[] = "description_old = {$extract('description')}";
        if (Schema::hasColumn('events', 'slug'))              $updates[] = "slug_old = {$extract('slug')}";

        if (! empty($updates)) {
            $sql = "UPDATE events SET " . implode(", ", $updates);
            DB::statement($sql);
        }

        // Drop JSON columns and rename *_old back
        Schema::table('events', function (Blueprint $table) {
            foreach (['title','subtitle','short_description','description','slug'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $map = [
                'title_old'             => 'title',
                'subtitle_old'          => 'subtitle',
                'short_description_old' => 'short_description',
                'description_old'       => 'description',
                'slug_old'              => 'slug',
            ];

            foreach ($map as $from => $to) {
                if (Schema::hasColumn('events', $from) && ! Schema::hasColumn('events', $to)) {
                    $table->renameColumn($from, $to);
                }
            }
        });
    }
};
