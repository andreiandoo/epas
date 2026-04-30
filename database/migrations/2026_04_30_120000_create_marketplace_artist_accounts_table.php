<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_artist_accounts')) {
            return;
        }

        Schema::create('marketplace_artist_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();

            // The Artist content profile this account claims/owns. Nullable until
            // an admin links it (or until the applicant supplied a slug at register).
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();

            // Account credentials
            $table->string('email');
            $table->string('password');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 50)->nullable();
            $table->string('locale', 5)->default('ro');

            // Approval workflow
            // pending  -> just registered, waiting for email verification + admin approval
            // active   -> approved by admin, can log in
            // rejected -> admin rejected the claim
            // suspended-> previously active, blocked by admin
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Claim metadata - what the applicant supplied to prove ownership
            $table->text('claim_message')->nullable();
            $table->json('claim_proof')->nullable();
            $table->timestamp('claim_submitted_at')->nullable();

            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();

            // Login tracking
            $table->timestamp('last_login_at')->nullable();

            // Per-account settings (notification prefs, profile completion flags, etc.)
            $table->json('settings')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // One email can have separate accounts on different marketplaces
            $table->unique(['marketplace_client_id', 'email'], 'mpa_client_email_unique');
            $table->index(['marketplace_client_id', 'status'], 'mpa_client_status_idx');
            $table->index('artist_id', 'mpa_artist_id_idx');
            $table->index('email_verification_token', 'mpa_email_verif_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_artist_accounts');
    }
};
