<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_pages', function (Blueprint $table) {
            $table->string('page_type')->default('content')->after('slug');
            $table->json('layout')->nullable()->after('content');
            $table->boolean('is_system')->default(false)->after('is_published');
            $table->string('seo_title')->nullable()->after('meta');
            $table->text('seo_description')->nullable()->after('seo_title');
        });

        // Add index for page_type lookups
        Schema::table('tenant_pages', function (Blueprint $table) {
            $table->index(['tenant_id', 'page_type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_pages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'page_type', 'is_published']);
            $table->dropColumn(['page_type', 'layout', 'is_system', 'seo_title', 'seo_description']);
        });
    }
};
