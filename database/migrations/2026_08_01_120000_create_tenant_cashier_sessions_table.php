<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier shifts for a leisure TENANT's operator panel.
 *
 * leisure_cashier_sessions cannot be reused: marketplace_client_id and
 * marketplace_organizer_id are NOT NULL with foreign keys into the marketplace
 * tables, and team_member_id points at marketplace_organizer_team_members. A
 * tenant operator has none of those. Same shape, tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_cashier_sessions')) {
            return;
        }

        Schema::create('tenant_cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('team_member_id')->nullable()
                ->constrained('tenant_team_members')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('opened_label', 128)->nullable();   // e.g. "Casa 1", or the operator's name
            $table->unsignedBigInteger('opening_float_cents')->default(0);
            $table->json('closing_snapshot')->nullable();      // counted cash, totals per method
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            // "the open session for this tenant" is the hot lookup
            $table->index(['tenant_id', 'closed_at'], 'tcs_tenant_open_idx');
            $table->index(['tenant_id', 'opened_at'], 'tcs_tenant_opened_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_cashier_sessions');
    }
};
