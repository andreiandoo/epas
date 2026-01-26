<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketplace_organizer_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable(); // Null until invite is accepted
            $table->enum('role', ['admin', 'manager', 'staff'])->default('staff');
            $table->json('permissions')->nullable(); // ['events', 'orders', 'reports', 'team', 'checkin']
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');
            $table->string('invite_token', 64)->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Foreign key with shorter name to avoid MySQL 64-char limit
            $table->foreign('marketplace_organizer_id', 'mp_org_team_org_id_fk')
                  ->references('id')
                  ->on('marketplace_organizers')
                  ->onDelete('cascade');

            // Unique email per organizer
            $table->unique(['marketplace_organizer_id', 'email'], 'mp_org_team_org_email_unique');

            // Index for faster lookups
            $table->index(['marketplace_organizer_id', 'status'], 'mp_org_team_org_status_idx');
            $table->index('invite_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_team_members');
    }
};
