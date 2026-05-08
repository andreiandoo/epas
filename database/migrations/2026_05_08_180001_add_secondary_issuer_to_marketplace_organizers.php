<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A doua societate emitenta pe profilul organizatorului.
 *
 * Permite ca un organizer sa emita facturi pe 2 societati distincte legate de
 * acelasi profil (ex: Lacul Sf. Ana — SC pentru bilete acces + SC pentru servicii).
 *
 * Toate campurile sunt nullable; has_secondary_issuer=false (default) -> zero schimbare
 * pentru organizers existenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'has_secondary_issuer')) {
                $table->boolean('has_secondary_issuer')->default(false)->after('iban');
            }

            // Date juridice societate secundara
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_name')) {
                $table->string('secondary_company_name', 255)->nullable()->after('has_secondary_issuer');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_tax_id')) {
                $table->string('secondary_company_tax_id', 32)->nullable()->after('secondary_company_name');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_registration')) {
                $table->string('secondary_company_registration', 32)->nullable()->after('secondary_company_tax_id');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_address')) {
                $table->text('secondary_company_address')->nullable()->after('secondary_company_registration');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_city')) {
                $table->string('secondary_company_city', 100)->nullable()->after('secondary_company_address');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_county')) {
                $table->string('secondary_company_county', 100)->nullable()->after('secondary_company_city');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_company_zip')) {
                $table->string('secondary_company_zip', 20)->nullable()->after('secondary_company_county');
            }

            // Cont bancar societate secundara
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_bank_name')) {
                $table->string('secondary_bank_name', 255)->nullable()->after('secondary_company_zip');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_iban')) {
                $table->string('secondary_iban', 34)->nullable()->after('secondary_bank_name');
            }

            // Numerotare facturi separata per societate
            if (!Schema::hasColumn('marketplace_organizers', 'primary_invoice_series')) {
                $table->string('primary_invoice_series', 16)->nullable()->after('secondary_iban');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'primary_last_invoice_number')) {
                $table->unsignedInteger('primary_last_invoice_number')->default(0)->after('primary_invoice_series');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_invoice_series')) {
                $table->string('secondary_invoice_series', 16)->nullable()->after('primary_last_invoice_number');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_last_invoice_number')) {
                $table->unsignedInteger('secondary_last_invoice_number')->default(0)->after('secondary_invoice_series');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $cols = [
                'secondary_last_invoice_number',
                'secondary_invoice_series',
                'primary_last_invoice_number',
                'primary_invoice_series',
                'secondary_iban',
                'secondary_bank_name',
                'secondary_company_zip',
                'secondary_company_county',
                'secondary_company_city',
                'secondary_company_address',
                'secondary_company_registration',
                'secondary_company_tax_id',
                'secondary_company_name',
                'has_secondary_issuer',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('marketplace_organizers', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
