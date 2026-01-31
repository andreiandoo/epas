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
        // Add marketplace_client_id to blog_categories (if not exists)
        if (!Schema::hasColumn('blog_categories', 'marketplace_client_id')) {
            Schema::table('blog_categories', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            });
        }

        // Add unique constraint for marketplace + slug (if not exists)
        // Check by trying to add - will be caught if exists
        try {
            Schema::table('blog_categories', function (Blueprint $table) {
                $table->unique(['marketplace_client_id', 'slug'], 'blog_categories_marketplace_slug_unique');
            });
        } catch (\Exception $e) {
            // Index already exists, skip
        }

        // Add marketplace_client_id to blog_articles (if not exists)
        if (!Schema::hasColumn('blog_articles', 'marketplace_client_id')) {
            Schema::table('blog_articles', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            });
        }

        // Add index for marketplace articles (if not exists)
        try {
            Schema::table('blog_articles', function (Blueprint $table) {
                $table->index(['marketplace_client_id', 'status', 'published_at'], 'blog_articles_marketplace_status_idx');
            });
        } catch (\Exception $e) {
            // Index already exists, skip
        }

        // Add marketplace_client_id to blog_tags (if not exists)
        if (!Schema::hasColumn('blog_tags', 'marketplace_client_id')) {
            Schema::table('blog_tags', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            });
        }

        // Add marketplace_client_id to blog_authors (if not exists)
        if (!Schema::hasColumn('blog_authors', 'marketplace_client_id')) {
            Schema::table('blog_authors', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('blog_authors', 'marketplace_client_id')) {
            Schema::table('blog_authors', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }

        if (Schema::hasColumn('blog_tags', 'marketplace_client_id')) {
            Schema::table('blog_tags', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }

        if (Schema::hasColumn('blog_articles', 'marketplace_client_id')) {
            Schema::table('blog_articles', function (Blueprint $table) {
                try {
                    $table->dropIndex('blog_articles_marketplace_status_idx');
                } catch (\Exception $e) {
                    // Index doesn't exist, skip
                }
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }

        if (Schema::hasColumn('blog_categories', 'marketplace_client_id')) {
            Schema::table('blog_categories', function (Blueprint $table) {
                try {
                    $table->dropUnique('blog_categories_marketplace_slug_unique');
                } catch (\Exception $e) {
                    // Index doesn't exist, skip
                }
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }
    }
};
