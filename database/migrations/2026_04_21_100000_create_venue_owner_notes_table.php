<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_owner_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('target_type', 32); // 'ticket' | 'order' | 'customer'
            $table->unsignedBigInteger('target_id');
            $table->text('note');
            $table->timestamps();

            $table->foreign('tenant_id', 'venue_owner_notes_tenant_fk')
                  ->references('id')
                  ->on('tenants')
                  ->onDelete('cascade');

            $table->foreign('user_id', 'venue_owner_notes_user_fk')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'target_type', 'target_id'], 'venue_owner_notes_target_idx');
            $table->index(['target_type', 'target_id'], 'venue_owner_notes_public_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_owner_notes');
    }
};
