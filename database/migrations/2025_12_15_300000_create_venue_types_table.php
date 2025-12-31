<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_types', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // Emoji icon like 🏛️
            $table->json('description')->nullable(); // Translatable
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_types');
    }
};
