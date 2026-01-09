<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('artists')) {
            return;
        }

        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            $table->string('country', 64)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('genres')->nullable();
            $table->jsonb('socials')->nullable();
            $table->string('status', 32)->default('active'); // active|inactive
            $table->timestamps();

            $table->index('status');
        });

        if (Schema::hasTable('event_artist')) {
            return;
        }

        Schema::create('event_artist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'artist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_artist');
        Schema::dropIfExists('artists');
    }
};
