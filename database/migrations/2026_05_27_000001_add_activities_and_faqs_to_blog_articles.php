<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog articles (guides) — add per-guide Linked Activities + FAQs.
 *
 *   activity_ids — JSON array of activity ids promoted in the guide
 *                  (alongside the existing single event_id). The guide
 *                  page renders them as "Vezi bilete" recommendation cards.
 *   faqs         — JSON array of {q, a} pairs. When empty, the public
 *                  guide page renders a fallback FAQ set from the template.
 *                  Also surfaced as FAQPage JSON-LD for SEO.
 *
 * Both nullable + additive — existing articles validate unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        Schema::table('blog_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_articles', 'activity_ids')) {
                $table->json('activity_ids')->nullable()->after('event_id');
            }
            if (! Schema::hasColumn('blog_articles', 'faqs')) {
                $table->json('faqs')->nullable()->after('activity_ids');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        Schema::table('blog_articles', function (Blueprint $table) {
            if (Schema::hasColumn('blog_articles', 'activity_ids')) {
                $table->dropColumn('activity_ids');
            }
            if (Schema::hasColumn('blog_articles', 'faqs')) {
                $table->dropColumn('faqs');
            }
        });
    }
};
