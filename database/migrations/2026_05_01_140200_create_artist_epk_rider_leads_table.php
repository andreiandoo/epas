<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('artist_epk_rider_leads')) {
            return;
        }

        Schema::create('artist_epk_rider_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_epk_variant_id')
                ->constrained('artist_epk_variants')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            $table->string('email', 200);
            $table->string('phone', 50)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('downloaded_at')->nullable();

            $table->timestamps();

            $table->index(['artist_epk_variant_id', 'downloaded_at'], 'aerl_variant_downloaded_idx');
            $table->index('email', 'aerl_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_epk_rider_leads');
    }
};
