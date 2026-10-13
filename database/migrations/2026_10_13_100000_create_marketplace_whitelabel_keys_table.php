<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API keys handed to organizers for their own whitelabel site.
     *
     * A whitelabel site runs on the organizer's server, so it must never get
     * the marketplace API key. These keys only open the handful of endpoints
     * such a site needs, limited to the organizer's own events, and each one
     * can be replaced without touching the marketplace or other organizers.
     */
    public function up(): void
    {
        Schema::create('marketplace_whitelabel_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            // One key per organizer; regenerating replaces it in place.
            $table->foreignId('marketplace_organizer_id')->unique()->constrained('marketplace_organizers')->cascadeOnDelete();

            // sha256 of the key: what incoming requests are matched against.
            $table->char('key_hash', 64)->unique();
            // The key itself (encrypted), so the package can be downloaded again
            // without invalidating the site that is already installed.
            $table->text('secret');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_whitelabel_keys');
    }
};
