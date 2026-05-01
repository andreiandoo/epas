<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('artist_epks')) {
            return;
        }

        Schema::create('artist_epks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')
                ->unique() // un singur EPK per artist
                ->constrained('artists')
                ->cascadeOnDelete();

            // Variantă activă afișată la /epk/{artist_slug} (fără variant slug). FK adăugat
            // dupa create al artist_epk_variants pentru a evita ordering issues.
            $table->unsignedBigInteger('active_variant_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_epks');
    }
};
