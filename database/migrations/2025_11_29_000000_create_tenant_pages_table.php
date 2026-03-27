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
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tenant_pages')->nullOnDelete();
            $table->json('title')->nullable();
            $table->string('slug');
            $table->json('content')->nullable();
            $table->string('menu_location')->nullable();
            $table->integer('menu_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pages');
    }
};
