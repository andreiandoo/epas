<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('artist_type_artist_genre')) {
            Schema::create('artist_type_artist_genre', function (Blueprint $table) {
                $table->unsignedBigInteger('artist_type_id');
                $table->unsignedBigInteger('artist_genre_id');
                $table->primary(['artist_type_id', 'artist_genre_id'], 'artist_type_genre_pk');

                $table->foreign('artist_type_id')->references('id')->on('artist_types')->cascadeOnDelete();
                $table->foreign('artist_genre_id')->references('id')->on('artist_genres')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('artist_type_artist_genre')) {
            Schema::drop('artist_type_artist_genre');
        }
    }
};
