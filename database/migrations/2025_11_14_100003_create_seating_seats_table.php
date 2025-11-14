<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('seating_rows')->onDelete('cascade');
            $table->string('label', 10);
            $table->decimal('x', 10, 2);
            $table->decimal('y', 10, 2);
            $table->decimal('angle', 10, 2)->default(0);
            $table->enum('shape', ['circle', 'rect', 'stadium'])->default('circle');
            $table->string('seat_uid', 32)->unique();
            $table->timestamps();

            $table->index('row_id');
            $table->unique(['row_id', 'seat_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_seats');
    }
};
