<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitatea aplicației Tixello.
 *
 * DE CE UN TABEL NOU
 * Pana acum existau doua lumi de conturi, complet separate: `customers`
 * (per tenant, cu CustomerToken) si `marketplace_customers` (per marketplace,
 * cu Sanctum). Un email pe doua marketplace-uri inseamna doua inregistrari
 * fara nicio legatura intre ele.
 *
 * Aplicatia Tixello nu apartine niciunei lumi: ea vinde la tot ce e in sistem.
 * Deci are nevoie de o identitate proprie, iar legaturile catre conturile din
 * lumile de mai sus stau separat, in `tixello_account_links`.
 *
 * Regula de aur a designului (vezi discutia de arhitectura): legatura se face
 * DOAR cu acordul explicit al omului. Aplicatia nu interogheaza niciodata
 * bazele partenerilor ca sa afle unde mai are cineva cont — ar fi si
 * enumerare de conturi, si dezvaluirea relatiei dintre platforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('email')->unique();
            $table->string('password')->nullable();   // null cat timp intra doar prin cod pe email
            $table->string('name')->nullable();
            $table->string('phone')->nullable();

            /**
             * Verificarea emailului NU e optionala aici.
             * Emailul e cheia care leaga o comanda facuta in aplicatie de contul
             * de la partener. Fara verificare, cineva se inregistreaza cu adresa
             * altcuiva si comenzile aterizeaza in contul victimei.
             */
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_code', 12)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);

            /** 'customer' sau 'organizer' — un cont poate fi ambele in timp */
            $table->boolean('is_organizer')->default(false);

            $table->string('avatar')->nullable();
            $table->string('locale', 5)->default('ro');
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('tixello_account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tixello_account_id')->constrained()->cascadeOnDelete();
            // se pastreaza hash-ul, nu tokenul — ca la CustomerToken
            $table->string('token', 64)->unique();
            $table->string('name')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tixello_account_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_account_tokens');
        Schema::dropIfExists('tixello_accounts');
    }
};
