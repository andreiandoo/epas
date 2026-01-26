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
            $table->foreignId('marketplace_organizer_id')->constrained()->onDelete('cascade');
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

            // Unique email per organizer
            $table->unique(['marketplace_organizer_id', 'email']);

            // Index for faster lookups
            $table->index(['marketplace_organizer_id', 'status']);
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
