<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_type_event_genre')) {
            Schema::create('event_type_event_genre', function (Blueprint $table) {
                $table->unsignedBigInteger('event_type_id');
                $table->unsignedBigInteger('event_genre_id');
                $table->primary(['event_type_id', 'event_genre_id'], 'event_type_genre_pk');

                $table->foreign('event_type_id')->references('id')->on('event_types')->cascadeOnDelete();
                $table->foreign('event_genre_id')->references('id')->on('event_genres')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_type_event_genre')) {
            Schema::drop('event_type_event_genre');
        }
    }
};
