<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shorts — vertical short-form video for the Tixello mobile app.
 *
 * Curated centrally in core admin, attachable to any event/umbrella owner.
 * Deliberately WITHOUT a global tenant scope on the model (same stance as
 * media_library): core admin sees everything, scoping happens in resources.
 *
 * Status/source/cta_type are plain strings rather than DB enums so later
 * phases can add values without a destructive column rewrite (see DECISIONS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shorts')) {
            return;
        }

        Schema::create('shorts', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (nullable = editorial short)
            $table->nullableMorphs('owner');   // Event|Activity|Attraction|Artist|Tenant

            // Umbrella scoping (denormalised for the dominant queries)
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('marketplace_client_id')->nullable()->index();
            $table->foreignId('event_id')->nullable()->index();

            // Source & pipeline
            $table->string('source', 32)->default('upload'); // upload|youtube|tiktok|instagram|facebook
            $table->text('source_url')->nullable();
            $table->string('source_video_id')->nullable();
            $table->longText('embed_html')->nullable();      // external only

            // Native / managed video
            $table->string('video_provider', 32)->nullable(); // bunny|cloudflare|mux|self
            $table->string('provider_asset_id')->nullable();
            $table->text('hls_url')->nullable();
            $table->boolean('ready')->default(false);
            $table->string('disk')->nullable();               // self-host fallback
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('duration')->nullable();  // seconds
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Presentation / feed
            $table->string('poster_path')->nullable();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('music_credit')->nullable();

            // Commerce (shoppable — see B1)
            $table->string('cta_type', 32)->default('none');  // none|buy_tickets|open_event|open_artist|external
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->foreignId('cta_ticket_type_id')->nullable();
            $table->string('promo_code')->nullable();

            // Publishing & moderation
            $table->string('status', 32)->default('draft');   // draft|pending_review|published|archived|rejected
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Denormalised stats (aggregated from short_events)
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('completions')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('cta_clicks')->default(0);
            $table->decimal('avg_watch_ratio', 4, 3)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['is_featured', 'status']);
            $table->index(['source', 'source_video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shorts');
    }
};
