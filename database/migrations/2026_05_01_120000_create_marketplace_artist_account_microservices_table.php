<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_artist_account_microservices')) {
            return;
        }

        Schema::create('marketplace_artist_account_microservices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_artist_account_id')
                ->constrained('marketplace_artist_accounts')
                ->cascadeOnDelete();

            $table->foreignId('microservice_id')
                ->constrained('microservices')
                ->cascadeOnDelete();

            // active   -> serviciu activ (admin override SAU plătit + curent)
            // trial    -> in perioada de trial gratuit
            // expired  -> expirat (trial sau abonament neînnoit)
            // cancelled-> artistul a cancelat (păstrează acces până la expires_at, apoi devine expired)
            // suspended-> blocat manual de admin (rar)
            $table->string('status', 20)->default('inactive');

            // admin_override -> activat manual de marketplace admin (fără plată, fără expirare)
            // self_purchase  -> plătit de artist via service_orders
            // trial          -> trial gratuit auto-activat
            $table->string('granted_by', 20)->nullable();

            // User-ul (admin) care a făcut admin_override; null pentru self_purchase/trial.
            // FK către marketplace_admins (panel-ul marketplace folosește un alt guard) ar
            // încălca consistency cu approved_by din marketplace_artist_accounts care nu are FK
            // tot din motivul ăsta — păstrăm fără constraint.
            $table->unsignedBigInteger('granted_by_user_id')->nullable();

            // Link la comanda de plată (self_purchase). Indexed pentru lookup invers.
            $table->foreignId('service_order_id')
                ->nullable()
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Per-artist preferences pentru module ulterioare (ex: notificări).
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->unique(
                ['marketplace_artist_account_id', 'microservice_id'],
                'maam_account_microservice_unique'
            );

            $table->index(['microservice_id', 'status'], 'maam_microservice_status_idx');
            $table->index('expires_at', 'maam_expires_idx');
            $table->index('trial_ends_at', 'maam_trial_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_artist_account_microservices');
    }
};
