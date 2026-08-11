<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `verification_code` trebuie să încapă un HASH, nu codul în clar.
 *
 * Coloana a fost declarată `varchar(12)` — cât un cod de 6 cifre — dar
 * `TixelloAccount::issueVerificationCode()` scrie `Hash::make($code)`, adică un
 * bcrypt de 60 de caractere. Pe Postgres asta e o eroare ("value too long"), nu
 * o trunchiere tăcută, aşa că ORICE înregistrare în aplicaţie răspundea 500.
 *
 * Codul se păstrează hashuit intenţionat: în clar, oricine are acces de citire
 * la baza de date poate verifica orice cont. Deci se lărgeşte coloana, nu se
 * renunţă la hash.
 *
 * 255, nu 60: bcrypt dă 60 azi, dar `Hash::make` urmează driverul configurat —
 * argon2id dă mai mult, iar o schimbare de driver n-are de ce să reproducă
 * acelaşi 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tixello_accounts')) {
            return;
        }

        Schema::table('tixello_accounts', function (Blueprint $table) {
            $table->string('verification_code', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        /* Nu se îngustează la loc: ar tăia hash-urile existente şi ar face
           imposibilă verificarea conturilor în aşteptare. */
    }
};
