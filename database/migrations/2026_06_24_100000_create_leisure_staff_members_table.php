<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff permanent al organizatorilor leisure (Sf. Ana, Sibiu Bazna etc.):
 * angajații care vin pe tură au QR fix, individual, care nu expiră — pot trece
 * de check-in oricâte ori vor. Tracking-ul accesărilor lor (când vin/pleacă)
 * trăiește în `leisure_staff_checkins` pentru raport pontaj + audit.
 *
 * Atașat la marketplace_organizer (nu la event), pentru că staff-ul poate
 * lucra simultan pe mai multe evenimente ale aceluiași organizator (toate
 * locațiile de agrement deținute). `event_id` nullable pe checkins înregistrează
 * unde s-a întâmplat scanarea.
 *
 * QR code generat la create: format `STAFF-{12 hex chars}` — scanner-ul detectează
 * prefix-ul și trece direct pe ruta de staff (nu cea de bilete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_staff_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('qr_code', 64)->unique(); // STAFF-{uuid12}
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('marketplace_organizer_id', 'leisure_staff_org_idx');
            $table->index('active', 'leisure_staff_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_staff_members');
    }
};
