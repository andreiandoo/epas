<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_categories')) {
            return;
        }

        Schema::create('venue_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable: en, ro
            $table->string('slug', 100)->unique();
            $table->string('icon', 10)->nullable(); // Emoji
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_categories');
    }
};
