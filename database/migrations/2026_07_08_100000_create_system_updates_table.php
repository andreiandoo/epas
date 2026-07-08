<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_updates')) {
            return;
        }

        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            // Marketplace ownership — an update is authored inside a
            // marketplace admin and is only visible on that marketplace's
            // public site. Enforced by the model's global scope + the
            // public API's X-API-Key resolver.
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255);
            // 3 fixed categories per product spec: interfata / organizator / client.
            // Enum enforced at the model / form layer; DB uses a plain string
            // so future categories don't require a schema migration.
            $table->string('category', 32);
            $table->string('status', 32)->default('draft');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            // SEO overrides — when empty the public page falls back to
            // title / excerpt.
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();

            // Slug is unique within a marketplace (two marketplaces can each
            // have a "welcome-la-noul-editor" update). Public URL routes
            // to the update via the marketplace's own domain + slug.
            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['marketplace_client_id', 'status', 'published_at']);
            $table->index(['marketplace_client_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
