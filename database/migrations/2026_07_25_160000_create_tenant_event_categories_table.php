<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_event_categories')) {
            Schema::create('tenant_event_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                // jsonb (not json): Postgres can't SELECT DISTINCT over json columns,
                // which Filament's belongsToMany relationship Select does when resolving
                // option labels. jsonb has equality operators, json does not.
                $table->jsonb('name');
                $table->string('slug');
                $table->string('icon', 64)->nullable();
                $table->string('image')->nullable();
                $table->jsonb('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('event_tenant_event_category')) {
            Schema::create('event_tenant_event_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_event_category_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['event_id', 'tenant_event_category_id'], 'event_tenant_cat_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tenant_event_category');
        Schema::dropIfExists('tenant_event_categories');
    }
};
