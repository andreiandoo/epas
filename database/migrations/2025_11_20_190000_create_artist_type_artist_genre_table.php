<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_type_artist_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_genre_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['artist_type_id', 'artist_genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_type_artist_genre');
    }
};
