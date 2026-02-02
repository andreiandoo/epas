<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Blog categories
        if (Schema::hasTable('blog_categories')) {
            return;
        }

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('blog_categories')->onDelete('set null');
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable(); // Hex color
            $table->json('meta_title')->nullable(); // Translatable
            $table->json('meta_description')->nullable(); // Translatable
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->integer('article_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_visible', 'sort_order']);
            $table->index('parent_id');
        });

        // Blog tags
        if (Schema::hasTable('blog_tags')) {
            return;
        }

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->integer('article_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'article_count']);
        });

        // Blog authors
        if (Schema::hasTable('blog_authors')) {
            return;
        }

        Schema::create('blog_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('slug');
            $table->string('email')->nullable();
            $table->text('bio')->nullable();
            $table->string('short_bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('twitter_handle')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('article_count')->default(0);
            $table->integer('total_views')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Blog series (article collections)
        if (Schema::hasTable('blog_series')) {
            return;
        }

        Schema::create('blog_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->json('description')->nullable(); // Translatable
            $table->string('cover_image_url')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->integer('article_count')->default(0);
            $table->json('meta_title')->nullable(); // Translatable
            $table->json('meta_description')->nullable(); // Translatable
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_visible']);
        });

        // Blog articles
        if (Schema::hasTable('blog_articles')) {
            return;
        }

        Schema::create('blog_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('slug');

            // Content
            $table->json('title'); // Translatable
            $table->json('subtitle')->nullable(); // Translatable
            $table->json('content')->nullable(); // Translatable - markdown or HTML
            $table->json('content_html')->nullable(); // Translatable - rendered HTML
            $table->json('content_json')->nullable(); // Structured content for editors like TipTap
            $table->json('excerpt')->nullable(); // Translatable - summary/preview

            // Media
            $table->string('featured_image_url')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->string('featured_image_caption')->nullable();
            $table->json('gallery')->nullable(); // Array of image URLs

            // Classification
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->onDelete('set null');
            $table->foreignId('series_id')->nullable()->constrained('blog_series')->onDelete('set null');
            $table->integer('series_order')->nullable();

            // Author
            $table->foreignId('author_id')->nullable()->constrained('blog_authors')->onDelete('set null');
            $table->json('co_author_ids')->nullable();

            // Publishing
            $table->enum('status', ['draft', 'pending_review', 'scheduled', 'published', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'private', 'password_protected', 'members_only'])->default('public');
            $table->string('password')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            // SEO
            $table->json('meta_title')->nullable(); // Translatable
            $table->json('meta_description')->nullable(); // Translatable
            $table->string('canonical_url')->nullable();
            $table->json('og_title')->nullable(); // Translatable
            $table->json('og_description')->nullable(); // Translatable
            $table->string('og_image_url')->nullable();
            $table->enum('twitter_card', ['summary', 'summary_large_image'])->default('summary_large_image');
            $table->json('schema_markup')->nullable();
            $table->boolean('no_index')->default(false);

            // Settings
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->integer('reading_time_minutes')->nullable();
            $table->integer('word_count')->default(0);

            // Engagement
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('share_count')->default(0);
            $table->integer('comment_count')->default(0);

            // Localization
            $table->string('language', 5)->default('en');
            $table->json('translations')->nullable(); // { "es": article_id, "fr": article_id }

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at']);
            $table->index(['tenant_id', 'category_id', 'status']);
            $table->index(['tenant_id', 'author_id', 'status']);
            $table->index(['tenant_id', 'is_featured', 'status']);
            $table->index(['tenant_id', 'series_id', 'series_order']);
            $table->index('scheduled_at');
        });

        // Article-Tag pivot table
        if (Schema::hasTable('blog_article_tag')) {
            return;
        }

        Schema::create('blog_article_tag', function (Blueprint $table) {
            $table->uuid('article_id');
            $table->foreignId('tag_id')->constrained('blog_tags')->onDelete('cascade');
            $table->timestamps();

            $table->primary(['article_id', 'tag_id']);
            $table->foreign('article_id')->references('id')->on('blog_articles')->onDelete('cascade');
        });

        // Article revisions for version history
        if (Schema::hasTable('blog_article_revisions')) {
            return;
        }

        Schema::create('blog_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('article_id');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->integer('revision_number');
            $table->json('title');
            $table->json('content')->nullable();
            $table->json('content_json')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('blog_articles')->onDelete('cascade');
            $table->unique(['article_id', 'revision_number']);
            $table->index(['article_id', 'created_at']);
        });

        // Article views tracking
        if (Schema::hasTable('blog_article_views')) {
            return;
        }

        Schema::create('blog_article_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('article_id');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('visitor_id', 64)->nullable(); // Anonymous identifier
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('referrer')->nullable();
            $table->string('source')->nullable(); // utm_source
            $table->string('medium')->nullable(); // utm_medium
            $table->string('device', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->integer('time_on_page')->nullable(); // seconds
            $table->integer('scroll_depth')->nullable(); // percentage
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('blog_articles')->onDelete('cascade');
            $table->index(['article_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Blog comments
        if (Schema::hasTable('blog_comments')) {
            return;
        }

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->uuid('parent_id')->nullable(); // For replies

            // Author
            $table->enum('author_type', ['user', 'guest'])->default('guest');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();

            // Content
            $table->text('content');
            $table->text('content_html')->nullable();

            // Status
            $table->enum('status', ['pending', 'approved', 'spam', 'deleted'])->default('pending');

            // Engagement
            $table->integer('like_count')->default(0);

            // Moderation
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('blog_articles')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('blog_comments')->onDelete('cascade');
            $table->index(['article_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        // Blog newsletter subscriptions
        if (Schema::hasTable('blog_subscriptions')) {
            return;
        }

        Schema::create('blog_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('name')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'unsubscribed'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source')->nullable(); // Where they signed up
            $table->json('tags')->nullable();
            $table->string('confirmation_token', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_subscriptions');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_article_views');
        Schema::dropIfExists('blog_article_revisions');
        Schema::dropIfExists('blog_article_tag');
        Schema::dropIfExists('blog_articles');
        Schema::dropIfExists('blog_series');
        Schema::dropIfExists('blog_authors');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
