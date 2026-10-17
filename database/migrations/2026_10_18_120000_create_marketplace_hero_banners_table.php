<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bannere custom afișate în caruselul hero de pe homepage, amestecate
        // cu evenimentele recomandate. Orizontal = desktop, vertical = mobil.
        Schema::create('marketplace_hero_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('url', 1000);
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('image_horizontal')->nullable();
            $table->string('image_vertical')->nullable();
            // Poziția în carusel (1 = primul slide).
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_hero_banners');
    }
};
