<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log al fiecărei scanări de QR staff la check-in. Un rând per scanare —
 * inclusiv scanări duplicate consecutive (ex: angajatul iese și revine).
 * Acest table e sursa raportului de pontaj/activitate pentru organizatorii
 * leisure (Sf. Ana, etc.).
 *
 * `event_id` și `location` (poartă) salvate ca să poată raporta
 * activitate per eveniment/locație. `scanned_by_user_id` = ID-ul user-ului
 * de scanner app (NULL dacă scanner-ul nu cere autentificare).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_staff_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_member_id');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('scanned_by_user_id')->nullable();
            $table->string('location', 120)->nullable(); // ex: "Poarta 1"
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamps();

            $table->index('staff_member_id', 'leisure_chk_staff_idx');
            $table->index(['event_id', 'checked_in_at'], 'leisure_chk_event_time_idx');
            $table->index('checked_in_at', 'leisure_chk_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_staff_checkins');
    }
};
