<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_tour_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('cities')->nullable();
            $table->json('constraints')->nullable();
            $table->json('optimized_route')->nullable();
            $table->json('summary')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index('artist_id', 'ats_artist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_tour_scenarios');
    }
};
