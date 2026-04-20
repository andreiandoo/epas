<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_staff_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->enum('role', ['admin', 'manager', 'staff'])->default('staff');
            $table->json('permissions')->nullable();
            $table->enum('status', ['pending', 'active', 'inactive'])->default('pending');
            $table->string('invite_token', 64)->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'venue_staff_tenant_id_fk')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            $table->unique(['tenant_id', 'email'], 'venue_staff_tenant_email_unique');
            $table->index(['tenant_id', 'status'], 'venue_staff_tenant_status_idx');
            $table->index('invite_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_staff_members');
    }
};
