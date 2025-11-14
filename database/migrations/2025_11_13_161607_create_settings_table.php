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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Company Information
            $table->string('company_name')->nullable();
            $table->string('cui')->nullable()->comment('CUI/CIF');
            $table->string('reg_com')->nullable()->comment('Nr. Reg. Com.');
            $table->string('vat_number')->nullable()->comment('VAT Number');

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('RO');

            // Contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable(); // IBAN
            $table->string('bank_swift')->nullable();

            // Invoice Settings
            $table->string('invoice_prefix')->default('INV');
            $table->integer('invoice_next_number')->default(1);
            $table->string('invoice_series')->nullable();
            $table->integer('default_payment_terms_days')->default(5);

            // Logo & Branding
            $table->string('logo_path')->nullable();
            $table->string('invoice_footer')->nullable();

            // Meta
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
