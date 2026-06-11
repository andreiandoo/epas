<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add admin-managed SEO body block (RichEditor in Filament) + FAQ list
     * to event categories and cities so the frontend can render them
     * instead of generic hardcoded copy.
     *
     *   seo_body_title  — translatable JSON. Heading shown above the body
     *                     block (e.g. "Tot ce trebuie să știi despre
     *                     escape rooms").
     *   seo_body        — translatable JSON. Rich HTML body. Allowed: <p>,
     *                     <h2>, <h3>, <ul>, <li>, <strong>, <em>, <a>.
     *   faqs            — JSON array of {q, a} entries (NOT translatable
     *                     yet — the bilete.online marketplace is RO-only;
     *                     ambilet stays untouched). Repeater in Filament.
     */
    public function up(): void
    {
        Schema::table('marketplace_event_categories', function (Blueprint $table) {
            $table->json('seo_body_title')->nullable()->after('meta_description');
            $table->json('seo_body')->nullable()->after('seo_body_title');
            $table->json('faqs')->nullable()->after('seo_body');
        });

        Schema::table('marketplace_cities', function (Blueprint $table) {
            $table->json('seo_body_title')->nullable()->after('description');
            $table->json('seo_body')->nullable()->after('seo_body_title');
            $table->json('faqs')->nullable()->after('seo_body');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_event_categories', function (Blueprint $table) {
            $table->dropColumn(['seo_body_title', 'seo_body', 'faqs']);
        });

        Schema::table('marketplace_cities', function (Blueprint $table) {
            $table->dropColumn(['seo_body_title', 'seo_body', 'faqs']);
        });
    }
};
