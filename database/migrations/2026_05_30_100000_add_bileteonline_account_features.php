<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bilete.online account features (2026-05-30)
 *
 * Adds three things in one shot so we can deploy /cont/setari tabs end-to-end:
 *   1. 2FA columns on marketplace_customers (TOTP secret + confirmed_at + recovery codes)
 *   2. marketplace_customer_beneficiaries — saved family members / co-attendees
 *   3. marketplace_customer_gdpr_requests — async data-export & deletion tracking
 *
 * All additive / nullable — non-breaking for the live ambilet + tics + bilete.online traffic.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. 2FA columns on marketplace_customers ----
        Schema::table('marketplace_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_customers', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }
            if (! Schema::hasColumn('marketplace_customers', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('marketplace_customers', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
        });

        // ---- 2. Beneficiaries (Familie / co-attendees) ----
        if (! Schema::hasTable('marketplace_customer_beneficiaries')) {
            Schema::create('marketplace_customer_beneficiaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                $table->foreignId('marketplace_customer_id')->constrained('marketplace_customers')->cascadeOnDelete();

                $table->string('name', 150);
                $table->string('relation', 40)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('email', 200)->nullable();
                $table->string('phone', 30)->nullable();
                $table->json('interests')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->index(['marketplace_customer_id', 'is_active']);
                $table->index(['marketplace_client_id']);
            });
        }

        // ---- 3. GDPR requests (export + deletion audit trail) ----
        if (! Schema::hasTable('marketplace_customer_gdpr_requests')) {
            Schema::create('marketplace_customer_gdpr_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                $table->foreignId('marketplace_customer_id')->constrained('marketplace_customers')->cascadeOnDelete();

                $table->string('request_type', 20)->default('export'); // export | deletion | rectification
                $table->string('status', 20)->default('pending');      // pending | processing | completed | failed
                $table->string('export_file_path')->nullable();
                $table->string('export_token', 80)->nullable();        // signed download token
                $table->unsignedInteger('file_size_bytes')->nullable();
                $table->text('error_message')->nullable();

                $table->timestamp('requested_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('downloaded_at')->nullable();

                $table->timestamps();

                $table->index(['marketplace_customer_id', 'status']);
                $table->index(['marketplace_client_id', 'created_at']);
                $table->unique('export_token');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_gdpr_requests');
        Schema::dropIfExists('marketplace_customer_beneficiaries');

        Schema::table('marketplace_customers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_customers', 'two_factor_confirmed_at')) {
                $table->dropColumn('two_factor_confirmed_at');
            }
            if (Schema::hasColumn('marketplace_customers', 'two_factor_recovery_codes')) {
                $table->dropColumn('two_factor_recovery_codes');
            }
            if (Schema::hasColumn('marketplace_customers', 'two_factor_secret')) {
                $table->dropColumn('two_factor_secret');
            }
        });
    }
};
