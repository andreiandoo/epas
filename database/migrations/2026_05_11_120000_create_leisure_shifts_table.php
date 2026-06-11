<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('role', 32)->default('gate_scanner');
            $table->string('gate', 32)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('marketplace_organizer_id', 'lshifts_org_fk')
                ->references('id')->on('marketplace_organizers')->onDelete('cascade');
            $table->foreign('event_id', 'lshifts_event_fk')
                ->references('id')->on('events')->onDelete('cascade');
            $table->foreign('team_member_id', 'lshifts_member_fk')
                ->references('id')->on('marketplace_organizer_team_members')->onDelete('set null');

            $table->index(['event_id', 'start_at'], 'lshifts_event_start_idx');
            $table->index(['marketplace_organizer_id', 'start_at'], 'lshifts_org_start_idx');
            $table->index('team_member_id', 'lshifts_member_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_shifts');
    }
};
