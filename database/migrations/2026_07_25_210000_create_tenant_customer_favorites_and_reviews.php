<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favorite (spectacole/artiști) + recenzii pentru clienții tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_customer_favorites')) {
            Schema::create('tenant_customer_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('item_type', 20)->default('event'); // event|artist
                $table->unsignedBigInteger('item_id');
                $table->jsonb('meta')->nullable(); // titlu, categorie, slug, imagine
                $table->timestamps();

                $table->unique(['customer_id', 'item_type', 'item_id'], 'tcf_unique');
                $table->index(['tenant_id', 'customer_id']);
            });
        }

        if (! Schema::hasTable('tenant_reviews')) {
            Schema::create('tenant_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->string('status', 20)->default('pending'); // pending|published|rejected
                $table->boolean('is_anonymous')->default(false);
                $table->boolean('recommend')->default(true);
                $table->jsonb('aspects')->nullable(); // {show, cast, venue}
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['event_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_reviews');
        Schema::dropIfExists('tenant_customer_favorites');
    }
};
