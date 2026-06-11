<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            // Human-readable identifier shown in UI / emails. Unique per client,
            // generated post-insert from the row id (TKT-YYYY-000123).
            $table->string('ticket_number', 32)->nullable();

            // Polymorphic opener — lets us extend to customers later without
            // schema migration. Morph map: 'organizer' => MarketplaceOrganizer,
            // 'customer' => Customer.
            $table->string('opener_type', 32);
            $table->unsignedBigInteger('opener_id');

            $table->foreignId('support_department_id')
                ->constrained('support_departments')
                ->restrictOnDelete();
            $table->foreignId('support_problem_type_id')
                ->nullable()
                ->constrained('support_problem_types')
                ->nullOnDelete();

            // Staff member who owns the ticket (nullable = unassigned).
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('subject', 255);
            $table->enum('status', [
                'open',                 // Just created, no staff response
                'in_progress',          // Staff working on it
                'awaiting_organizer',   // Waiting for opener reply
                'resolved',             // Staff marked resolved (auto-closes after N days)
                'closed',               // Final state
            ])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal');

            // Form-specific fields for this ticket: url, invoice_series,
            // invoice_number, event_id, module_name, plus the free-form description.
            $table->json('meta')->nullable();
            // Captured request context: ip, user_agent, browser, os, device,
            // source_url, referer, language, screen_resolution.
            $table->json('context')->nullable();

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_client_id', 'ticket_number'], 'support_tkt_number_uq');
            $table->index(['marketplace_client_id', 'status', 'last_activity_at'], 'support_tkt_listing_idx');
            $table->index(['support_department_id', 'status'], 'support_tkt_dept_status_idx');
            $table->index(['opener_type', 'opener_id'], 'support_tkt_opener_idx');
            $table->index(['assigned_to_user_id', 'status'], 'support_tkt_assigned_idx');
            $table->index('resolved_at', 'support_tkt_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
