<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media-partners microservice — articles a partner publishes about artists
 * (e.g. Maximum Rock interviews), shown as short cards on the marketplace
 * artist page with a link to the partner's site. Written by the partner
 * through PUT /api/partner/v1/external-articles/{source}/{source_id}.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_partner_id')
                ->constrained('marketplace_partners')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // The article id on the partner's site.
            $table->string('source_id', 64);
            $table->string('type', 32)->nullable();
            $table->string('title', 255);
            $table->text('excerpt')->nullable();
            $table->string('url', 2048);
            $table->string('image_url', 2048)->nullable();
            $table->string('author', 191)->nullable();
            $table->timestamp('published_at');
            $table->timestamp('source_updated_at')->nullable();

            // Hidden by a marketplace admin; a later update from the partner keeps it hidden.
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['marketplace_partner_id', 'source_id']);
            $table->index(['marketplace_client_id', 'published_at']);
        });

        Schema::create('partner_article_artist', function (Blueprint $table) {
            $table->foreignId('partner_article_id')
                ->constrained('partner_articles')
                ->cascadeOnDelete();
            $table->foreignId('artist_id')
                ->constrained('artists')
                ->cascadeOnDelete();

            $table->primary(['partner_article_id', 'artist_id']);
            $table->index('artist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_article_artist');
        Schema::dropIfExists('partner_articles');
    }
};
