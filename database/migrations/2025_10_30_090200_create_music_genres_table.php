<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('music_genres')) {
            Schema::create('music_genres', function (Blueprint $table) {
                $table->id();
                $table->string('name', 190);
                $table->string('slug', 190)->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('music_genres')) {
            Schema::dropIfExists('music_genres');
        }
    }
};
