<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Biletul ăsta e pentru un prieten" — intenția, păstrată până la plată.
 *
 * DE CE O TABELĂ ȘI NU DIRECT UN TRANSFER
 * Transferul (`marketplace_ticket_transfers`) are nevoie de un `ticket_id`, iar
 * biletele nu există până când comanda nu e plătită. Intenția se scrie la
 * crearea comenzii, iar observatorul de comandă o transformă în transfer(uri)
 * abia când banii au intrat. Dacă plata eșuează, intenția rămâne `pending` și
 * nu produce nimic — nimeni nu primește un bilet pentru care nu s-a plătit.
 *
 * `quantity` și nu `ticket_id`: cumperi patru bilete și dai două unui prieten.
 * La conversie se iau primele N bilete neatribuite din comandă.
 *
 * Destinatarul poate fi un cont tics (`tixello_account_id`) sau doar o adresă:
 * transferul prin email există deja și funcționează pentru cine n-are cont.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_gift_intents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('marketplace_client_id')->nullable()->index();

            /* Cine dăruiește. Contul tics al cumpărătorului — comanda are deja
               clientul de marketplace, dar prietenii trăiesc pe conturile tics. */
            $table->unsignedBigInteger('tixello_account_id')->nullable()->index();

            /* Cine primește. Contul, când prietenul are unul; altfel adresa. */
            $table->unsignedBigInteger('recipient_account_id')->nullable()->index();
            $table->string('recipient_email', 190);
            $table->string('recipient_name', 120)->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->string('message', 500)->nullable();

            $table->enum('status', ['pending', 'converted', 'failed', 'cancelled'])->default('pending');
            /* De ce n-a mers, când n-a mers: fără asta, un cadou pierdut e
               imposibil de explicat clientului care întreabă. */
            $table->string('error', 300)->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_gift_intents');
    }
};
