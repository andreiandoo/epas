<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seating_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('venues')->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->integer('canvas_w')->default(1920);
            $table->integer('canvas_h')->default(1080);
            $table->string('background_image_path')->nullable();
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'venue_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seating_layouts');
    }
};
