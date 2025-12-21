<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the marketplace_organizer_users table.
     * These are users who can log into the organizer dashboard.
     */
    public function up(): void
    {
        Schema::create('marketplace_organizer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('marketplace_organizers')->cascadeOnDelete();

            // User credentials
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('phone')->nullable();

            // Role within the organizer: admin (full access), editor (manage events), viewer (read-only)
            $table->string('role')->default('admin');

            // Profile
            $table->string('avatar')->nullable();
            $table->string('position')->nullable(); // Job title

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // Two-factor authentication
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Unique email per organizer
            $table->unique(['organizer_id', 'email']);
            $table->index(['organizer_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_users');
    }
};
