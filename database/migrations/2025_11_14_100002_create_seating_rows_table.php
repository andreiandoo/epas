<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('seating_sections')->onDelete('cascade');
            $table->string('label', 10);
            $table->decimal('y', 10, 2)->default(0);
            $table->decimal('rotation', 10, 2)->default(0);
            $table->integer('seat_count')->default(0);
            $table->timestamps();

            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_rows');
    }
};
