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
        Schema::create('marketplace_organizer_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->string('bank_name');
            $table->string('iban', 34);
            $table->string('account_holder');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Foreign key with shorter name to avoid MySQL 64-char limit
            $table->foreign('marketplace_organizer_id', 'mp_org_bank_accounts_org_id_fk')
                  ->references('id')
                  ->on('marketplace_organizers')
                  ->onDelete('cascade');

            // Index for faster lookups
            $table->index(['marketplace_organizer_id', 'is_primary'], 'mp_org_bank_org_primary_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_bank_accounts');
    }
};
