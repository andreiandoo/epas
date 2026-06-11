<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_seating_layouts')) {
            return;
        }

        Schema::create('event_seating_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('layout_id')->nullable()->constrained('seating_layouts')->onDelete('set null');
            $table->json('json_geometry');
            $table->timestamp('published_at')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->index('event_id');
            $table->index(['event_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_seating_layouts');
    }
};
