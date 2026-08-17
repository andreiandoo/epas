<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token-uri pentru widget-ul de Android.
 *
 * Endpoint-ul `/api/tixello-widget/*` expune cifrele întregii platforme
 * (toţi tenanţii, toate marketplace-urile), deci nu poate sta pe cheia de
 * marketplace şi nici pe o sesiune de admin — telefonul are nevoie de un
 * secret cu viaţă lungă, revocabil separat.
 *
 * În DB se ţine DOAR `sha256(token)`. Valoarea în clar se vede o singură
 * dată, la generare (`php artisan tixello:widget-token`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_widget_tokens', function (Blueprint $table) {
            $table->id();
            /* La ce telefon a plecat token-ul — ca să ştii ce revoci. */
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            /* Revocare fără ştergere: rămâne urma în audit. */
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_widget_tokens');
    }
};
