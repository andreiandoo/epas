<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates tables for the Knowledge Base microservice.
     */
    public function up(): void
    {
        // Knowledge Base Categories
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('icon')->nullable(); // Heroicon name e.g. 'heroicon-o-ticket'
            $table->string('color')->nullable(); // Tailwind color e.g. 'red-500' or hex
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->integer('article_count')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'kb_cat_client_slug_unique');
            $table->index('is_visible');
        });

        // Knowledge Base Articles
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('kb_category_id')->constrained('kb_categories')->onDelete('cascade');
            $table->enum('type', ['article', 'faq'])->default('article'); // article or FAQ (question/answer)
            $table->json('title'); // Translatable
            $table->string('slug');
            $table->json('content')->nullable(); // Translatable - for article type, this is the body; for FAQ, this is the answer
            $table->json('question')->nullable(); // Translatable - only used for FAQ type
            $table->string('icon')->nullable(); // Optional icon for the article
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->json('meta_title')->nullable(); // Translatable SEO
            $table->json('meta_description')->nullable(); // Translatable SEO
            $table->json('tags')->nullable(); // Array of tags for search
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'kb_art_client_slug_unique');
            $table->index(['kb_category_id', 'is_visible']);
            $table->index(['marketplace_client_id', 'is_featured']);
            $table->index(['marketplace_client_id', 'is_popular']);
        });

        // Popular topics/searches
        Schema::create('kb_popular_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('topic');
            $table->string('url')->nullable(); // Link to article or search
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['marketplace_client_id', 'is_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kb_popular_topics');
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('kb_categories');
    }
};
