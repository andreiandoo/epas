<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('artist_fan_segments')) {
            return;
        }

        Schema::create('artist_fan_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();

            $table->string('name', 80);
            $table->text('description')->nullable();

            // criteria JSON: {events_min, events_max, spend_min, spend_max,
            // cities[], last_event_after, last_event_before, genres[]}
            $table->json('criteria')->nullable();

            $table->string('color', 9)->default('#A51C30'); // hex pentru UI

            $table->timestamps();

            $table->index('artist_id', 'afs_artist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_fan_segments');
    }
};
