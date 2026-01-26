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
            $table->foreignId('marketplace_organizer_id')->constrained()->onDelete('cascade');
            $table->string('bank_name');
            $table->string('iban', 34);
            $table->string('account_holder');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Index for faster lookups
            $table->index(['marketplace_organizer_id', 'is_primary']);
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
