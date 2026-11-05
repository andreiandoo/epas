<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ce știe tics.ro despre un eveniment din marketplace: pe ce alte platforme de ticketing
     * se mai vinde. Un rând per eveniment, rescris la fiecare sincronizare (tics:sync-listings).
     */
    public function up(): void
    {
        if (Schema::hasTable('tics_event_listings')) {
            return;
        }

        Schema::create('tics_event_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->index();
            // false = tics nu are (încă) evenimentul nostru în baza lui.
            $table->boolean('found')->default(false);
            $table->unsignedBigInteger('tics_event_id')->nullable();
            $table->string('tics_url', 500)->nullable();
            $table->float('match_confidence')->nullable();
            $table->json('platforms')->nullable();
            $table->unsignedSmallInteger('other_active_count')->default(0)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tics_event_listings');
    }
};
