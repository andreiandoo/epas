<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Înscrierea nouă (/inregistrare): cine facturează, aprobarea în core și acordul
 * pentru termeni. Toate coloanele sunt nullable; tenanții existenți au
 * approval_status = null și se comportă exact ca până acum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'legal_entity_type')) {
                $table->string('legal_entity_type', 10)->nullable(); // pf | pfa | srl
            }
            if (! Schema::hasColumn('tenants', 'identity_data')) {
                $table->text('identity_data')->nullable(); // criptat: CNP, CI (persoană fizică)
            }
            if (! Schema::hasColumn('tenants', 'approval_status')) {
                // null = tenant vechi; incomplete = înscriere în curs; pending; approved; rejected
                $table->string('approval_status', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('tenants', 'approval_submitted_at')) {
                $table->timestamp('approval_submitted_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'terms_version')) {
                $table->string('terms_version', 32)->nullable();
            }
            if (! Schema::hasColumn('tenants', 'terms_ip')) {
                $table->string('terms_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('tenants', 'signup_choices')) {
                $table->json('signup_choices')->nullable(); // site, template, plăți alese la înscriere
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'legal_entity_type', 'identity_data', 'approval_status', 'approval_submitted_at',
                'approved_at', 'approved_by', 'rejected_at', 'rejected_reason',
                'terms_accepted_at', 'terms_version', 'terms_ip', 'signup_choices',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
