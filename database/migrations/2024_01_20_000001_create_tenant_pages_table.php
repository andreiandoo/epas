<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('tenant_pages')->onDelete('set null');
            $table->json('title'); // Translatable
            $table->string('slug');
            $table->json('content')->nullable(); // Translatable WYSIWYG content
            $table->string('menu_location')->default('footer'); // 'header', 'footer', 'none'
            $table->integer('menu_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->json('meta')->nullable(); // SEO meta, etc.
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'menu_location', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pages');
    }
};
