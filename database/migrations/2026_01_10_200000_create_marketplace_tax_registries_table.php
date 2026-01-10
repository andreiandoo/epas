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
        Schema::create('marketplace_tax_registries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('country')->default('Romania');
            $table->string('county')->nullable();
            $table->string('city')->nullable();
            $table->string('name');
            $table->string('subname')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('cif')->nullable()->comment('Tax ID / CUI');
            $table->string('iban')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['marketplace_client_id', 'name']);
            $table->index(['marketplace_client_id', 'cif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_tax_registries');
    }
};
