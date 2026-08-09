<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legaturile dintre un cont Tixello si conturile din lumile existente.
 *
 * Un cont Tixello poate avea mai multe legaturi (un client care cumpara de la
 * mai multi vanzatori), dar exact UNA per (tip, sursa).
 *
 * ASIMETRIA care sta la baza intregului design:
 *
 *   SCRIERE (plasarea unei comenzi) — se potriveste dupa email in lumea
 *   vanzatorului si se REFOLOSESTE contul existent. Asa nu apar clienti dubli
 *   in CRM-ul partenerului. Checkout-ul de marketplace face deja asta
 *   (`firstOrCreate` pe `marketplace_client_id` + `email`), deci nu schimbam
 *   nimic acolo.
 *
 *   CITIRE (afisarea istoricului in aplicatie) — DOAR pe baza unei legaturi
 *   din tabelul asta, adica doar cu acordul explicit al omului. Altfel
 *   aplicatia ar arata cunoastere pe care n-ar trebui s-o aiba.
 *
 * `consent_source` pastreaza cum s-a nascut legatura, ca sa se poata dovedi
 * mai tarziu ca omul a cerut-o.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_account_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tixello_account_id')->constrained()->cascadeOnDelete();

            /** ce fel de cont din lumea veche e legat */
            $table->enum('kind', [
                'marketplace_customer',
                'marketplace_organizer',
                'tenant_customer',
                'tenant_user',
            ]);

            /** sursa: marketplace_client_id sau tenant_id, dupa `kind` */
            $table->unsignedBigInteger('marketplace_client_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();

            /** id-ul inregistrarii legate, in tabelul propriu tipului */
            $table->unsignedBigInteger('linked_id');

            /** cum a aparut legatura: dovada consimtamantului */
            $table->enum('consent_source', [
                'registration_optin',   // a bifat la inregistrare
                'manual_link',          // a legat-o din Profil
                'order_recovery',       // a atasat o comanda cu numar + email
                'organizer_login',      // s-a autentificat cu contul de organizator
            ]);
            $table->timestamp('consented_at')->useCurrent();
            $table->string('consent_ip', 45)->nullable();

            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // o singura legatura per cont si per inregistrare-tinta
            $table->unique(['tixello_account_id', 'kind', 'linked_id'], 'tx_link_unique');
            $table->index(['kind', 'linked_id']);
            $table->index(['marketplace_client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_account_links');
    }
};
