<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repertoire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('title')->comment('Translatable title');
            $table->string('slug')->index();
            $table->json('description')->nullable()->comment('Translatable description');
            $table->json('short_description')->nullable()->comment('Translatable short description');

            // Production details
            $table->integer('duration_minutes')->nullable();
            $table->string('genre')->nullable()->comment('Drama, comedy, opera, symphony, etc.');
            $table->string('age_rating')->nullable()->comment('Audience age rating, e.g. 12+, 16+, all_ages');
            $table->boolean('is_premiere')->default(false)->comment('Whether this is a premiere production');
            $table->date('premiere_date')->nullable();

            // Creative team (stored as strings for flexibility)
            $table->string('director')->nullable();
            $table->string('choreographer')->nullable();
            $table->string('conductor')->nullable();
            $table->string('set_designer')->nullable();
            $table->string('costume_designer')->nullable();
            $table->string('lighting_designer')->nullable();
            $table->string('librettist')->nullable()->comment('For opera');
            $table->string('composer')->nullable()->comment('For opera/philharmonic');

            // For philharmonic concert programs
            $table->json('program_pieces')->nullable()->comment('Array of pieces: [{title, composer, duration_minutes}]');

            // Media
            $table->string('poster_url')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->json('gallery')->nullable()->comment('Array of image URLs');

            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertoire');
    }
};
