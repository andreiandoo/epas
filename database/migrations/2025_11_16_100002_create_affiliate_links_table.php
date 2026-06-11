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
        if (Schema::hasTable('affiliate_links')) {
            return;
        }

        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
            $table->string('slug')->nullable(); // Optional custom slug
            $table->string('code'); // Link code (can be same as affiliate code or custom)
            $table->string('landing_url')->nullable(); // Optional landing page URL
            $table->json('meta')->nullable(); // UTM params, notes, etc.
            $table->timestamps();

            $table->index('affiliate_id');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
    }
};
