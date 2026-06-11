<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E4 — Tenant operators / staff. Mirror of marketplace_organizer_team_members
 * but scoped to a Tenant (for tenant_type=leisure and future direct-tenant
 * operational roles).
 *
 * user_id is REQUIRED — operators auth with their User email+password.
 * Permissions are role-based (leisure_role enum) + a free-form JSON array
 * for fine-grained checks ("can_void_orders" etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role')->default('staff'); // admin | manager | staff
            $table->string('leisure_role')->nullable(); // check_in | rental_operator | pos_cashier | inventory_manager | pos_manager | admin
            $table->json('permissions')->nullable(); // ["orders.view", "rentals.start", "pos.checkout", ...]

            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');
            $table->string('invite_token')->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('shift_data')->nullable(); // {date, start, end, location/gate}
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id'], 'ttm_tenant_user_unique');
            $table->index(['tenant_id', 'status', 'leisure_role'], 'ttm_tenant_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_team_members');
    }
};
