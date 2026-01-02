<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();

            // Action identification
            $table->string('action_type'); // ticket_purchase, event_checkin, review_submitted, referral_conversion, profile_complete, first_purchase

            // Naming (translatable)
            $table->json('name'); // e.g., {"en": "Ticket Purchase", "ro": "Cumpărare bilet"}
            $table->json('description')->nullable();

            // XP calculation
            $table->enum('xp_type', ['fixed', 'per_currency', 'multiplier'])->default('fixed');
            $table->integer('xp_amount')->default(0); // For fixed type
            $table->decimal('xp_per_currency_unit', 8, 2)->default(1); // For per_currency: XP per RON spent

            // Limits
            $table->integer('max_xp_per_action')->nullable(); // Cap on XP from single action
            $table->integer('max_times_per_day')->nullable(); // How many times can earn per day
            $table->integer('cooldown_hours')->nullable(); // Hours between earning from same action

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes (shortened names to avoid MySQL 64-char limit)
            $table->index(['tenant_id', 'action_type', 'is_active'], 'exp_actions_tenant_type_active_idx');
            $table->index(['marketplace_client_id', 'action_type', 'is_active'], 'exp_actions_mp_type_active_idx');
            $table->unique(['tenant_id', 'action_type'], 'exp_actions_tenant_type_unique');
            $table->unique(['marketplace_client_id', 'action_type'], 'exp_actions_mp_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_actions');
    }
};
