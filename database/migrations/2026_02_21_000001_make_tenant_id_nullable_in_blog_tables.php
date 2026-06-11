<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // blog_categories: make tenant_id nullable for marketplace-scoped categories
        if (Schema::hasColumn('blog_categories', 'tenant_id')) {
            Schema::table('blog_categories', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->change();
            });
        }

        // blog_articles: make tenant_id nullable for marketplace-scoped articles
        if (Schema::hasColumn('blog_articles', 'tenant_id')) {
            Schema::table('blog_articles', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Note: reverting would require all rows to have a tenant_id, which may not be possible
        // Left as no-op intentionally
    }
};
