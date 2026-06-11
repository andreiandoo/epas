<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_organizer_team_member_event', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_member_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamps();

            $table->foreign('team_member_id', 'mp_org_team_event_member_fk')
                  ->references('id')
                  ->on('marketplace_organizer_team_members')
                  ->onDelete('cascade');

            $table->foreign('event_id', 'mp_org_team_event_event_fk')
                  ->references('id')
                  ->on('events')
                  ->onDelete('cascade');

            $table->unique(['team_member_id', 'event_id'], 'mp_org_team_event_unique');
            $table->index('event_id', 'mp_org_team_event_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_team_member_event');
    }
};
