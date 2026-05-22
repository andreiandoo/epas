<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_city_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // Slug uniquely identifies the intent within the marketplace.
            // Convention: starts with `activitati-` so routing can distinguish
            // intent URLs from categories and city slugs without ambiguity.
            $table->string('slug', 120);

            // Translatable labels & template strings (JSON columns).
            // {placeholders} supported: {intent_label}, {city_name}, {result_count}
            $table->json('name');                          // short label, e.g. "Indoor"
            $table->json('title_template')->nullable();    // <title>
            $table->json('h1_template')->nullable();       // page <h1> — defaults to title
            $table->json('meta_description_template')->nullable();
            $table->json('intro_copy')->nullable();        // short paragraph above results
            $table->json('seo_copy')->nullable();          // long-form copy below results (markdown ok)

            // Filter DSL. Resolved server-side by IntentFilterResolver.
            // Shape: {"all":[{"type":"...","value":...}], "any":[...], "not":{...}}
            $table->json('filter_rule_json');

            // Presentation
            $table->string('icon', 80)->nullable();         // emoji or heroicon name
            $table->string('accent_color', 32)->nullable(); // vermilion|forest|ochre|sky
            $table->string('cover_image_url')->nullable();  // optional OG/hero image

            // SEO controls
            // Pages rendering fewer results than this get <meta robots="noindex"> to
            // dodge Google's "thin content" penalty. Keep small (3) to allow more
            // pages into the index in early days, raise later.
            $table->unsignedSmallInteger('min_results_for_index')->default(3);

            // Visibility & ordering
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['marketplace_client_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_city_intents');
    }
};
