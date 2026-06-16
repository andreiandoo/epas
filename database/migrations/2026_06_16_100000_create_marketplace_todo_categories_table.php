<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_todo_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('color', 24)->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'mp_todo_cat_slug_uq');
            $table->index(['marketplace_client_id', 'is_active', 'sort_order'], 'mp_todo_cat_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_todo_categories');
    }
};
