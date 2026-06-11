<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yoast-style keyword fields on blog articles:
 *   focus_keyword       — single primary keyword/phrase
 *   secondary_keywords  — comma-separated secondary keywords
 *   longtail_phrases    — comma-separated long-tail phrases
 * Postgres-safe + idempotent (ADD COLUMN IF NOT EXISTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE blog_articles ADD COLUMN IF NOT EXISTS focus_keyword VARCHAR(255) NULL');
        DB::statement('ALTER TABLE blog_articles ADD COLUMN IF NOT EXISTS secondary_keywords TEXT NULL');
        DB::statement('ALTER TABLE blog_articles ADD COLUMN IF NOT EXISTS longtail_phrases TEXT NULL');
    }

    public function down(): void
    {
        try { DB::statement('ALTER TABLE blog_articles DROP COLUMN IF EXISTS focus_keyword'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE blog_articles DROP COLUMN IF EXISTS secondary_keywords'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE blog_articles DROP COLUMN IF EXISTS longtail_phrases'); } catch (\Throwable $e) {}
    }
};
