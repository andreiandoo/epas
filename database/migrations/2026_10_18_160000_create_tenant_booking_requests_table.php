<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cererile de booking primite de o agenție de artiști (sau de un artist) prin site-ul
 * tenantului: cine cere, pentru ce artist, ce fel de eveniment, când, unde, ce buget.
 * Agenția le urmărește în panou (stare: nouă → în discuție → confirmată / refuzată).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_booking_requests')) {
            return;
        }
        Schema::create('tenant_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_artist_id')->nullable()->constrained('tenant_artists')->nullOnDelete();
            $table->string('artist_name', 190)->nullable(); // păstrat și dacă artistul e scos din roster
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('company', 190)->nullable();
            $table->string('event_type', 40)->nullable(); // concert | festival | private | corporate | other
            $table->date('event_date')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('venue', 190)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('budget', 60)->nullable();
            $table->text('message')->nullable();
            $table->string('locale', 5)->default('ro');
            $table->string('status', 20)->default('new'); // new | in_progress | won | lost
            $table->text('notes')->nullable(); // notițele interne ale agenției
            $table->string('consent_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_booking_requests');
    }
};
