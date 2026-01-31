<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add business details fields and fixed commission to marketplace_clients table.
     * These fields were missing but are required by the Settings page.
     */
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            // Business/Company details
            $table->string('cui', 50)->nullable()->after('company_name');
            $table->string('reg_com', 50)->nullable()->after('cui');
            $table->boolean('vat_payer')->default(false)->after('reg_com');
            $table->string('tax_display_mode')->default('included')->after('vat_payer');

            // Address fields
            $table->string('address')->nullable()->after('tax_display_mode');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->after('state');
            $table->string('postal_code', 20)->nullable()->after('country');

            // Bank details
            $table->string('bank_name')->nullable()->after('postal_code');
            $table->string('bank_account', 50)->nullable()->after('bank_name');
            $table->string('currency', 3)->default('EUR')->after('bank_account');

            // Website and ticket terms
            $table->string('website')->nullable()->after('currency');
            $table->text('ticket_terms')->nullable()->after('website');

            // Fixed commission (new feature - Comision Fix)
            $table->decimal('fixed_commission', 8, 2)->nullable()->after('commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn([
                'cui',
                'reg_com',
                'vat_payer',
                'tax_display_mode',
                'address',
                'city',
                'state',
                'country',
                'postal_code',
                'bank_name',
                'bank_account',
                'currency',
                'website',
                'ticket_terms',
                'fixed_commission',
            ]);
        });
    }
};
