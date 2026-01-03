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
        // Add marketplace_client_id to blog_categories
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->nullOnDelete();

            // Make tenant_id nullable for marketplace-only categories
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add unique constraint for marketplace + slug
            $table->unique(['marketplace_client_id', 'slug'], 'blog_categories_marketplace_slug_unique');
        });

        // Add marketplace_client_id to blog_articles
        Schema::table('blog_articles', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->nullOnDelete();

            // Make tenant_id nullable for marketplace-only articles
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add index for marketplace articles
            $table->index(['marketplace_client_id', 'status', 'published_at'], 'blog_articles_marketplace_status_idx');
        });

        // Add marketplace_client_id to blog_tags
        Schema::table('blog_tags', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->nullOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        // Add marketplace_client_id to blog_authors
        Schema::table('blog_authors', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                ->constrained('marketplace_clients')->nullOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_authors', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });

        Schema::table('blog_tags', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });

        Schema::table('blog_articles', function (Blueprint $table) {
            $table->dropIndex('blog_articles_marketplace_status_idx');
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropUnique('blog_categories_marketplace_slug_unique');
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });
    }
};
