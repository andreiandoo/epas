<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E3 — Leisure tenant: physical inventory items that can be rented out
 * (boats, kayaks, bikes, sleds, lockers …). Each row is one unit of
 * equipment with a printable QR sticker. Separate from the marketplace
 * `leisure_boats` table used by Ambilet (Sf. Ana) — that one stays
 * untouched and the two systems do not share data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type')->index();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('qr_code')->unique();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])
                ->default('available')
                ->index();
            $table->json('linked_ticket_type_ids')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'resource_type', 'status'], 'pr_tenant_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_resources');
    }
};
