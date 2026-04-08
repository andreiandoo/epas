<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_vanity_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('target_type'); // artist, event, venue, organizer, external_url
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'mp_vanity_unique');
            $table->index(['target_type', 'target_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vanity_urls');
    }
};
