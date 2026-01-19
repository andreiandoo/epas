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
        if (Schema::hasTable('affiliate_clicks')) {
            return;
        }

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('ip_hash')->nullable(); // Hashed IP for privacy
            $table->string('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->string('landing_url')->nullable();
            $table->json('utm_params')->nullable(); // UTM source, medium, campaign, etc.
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->index(['affiliate_id', 'clicked_at']);
            $table->index(['tenant_id', 'clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
    }
};
