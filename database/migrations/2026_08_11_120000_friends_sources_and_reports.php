<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Două completări la partea de prieteni.
 *
 * 1. SURSA devine text liber, nu enum.
 *
 * Enum-ul a fost o alegere strânsă: pe Postgres devine o constrângere CHECK, iar
 * fiecare valoare nouă cere ştergerea şi recrearea ei — cu blocare de tabel.
 * Apar deja două valori noi (`email` pentru invitaţiile scrise de mână, ca să nu
 * mai fie confundate cu cele prin cod) şi vor mai apărea. Valorile rămân
 * validate în cod, unde e ieftin de schimbat.
 *
 * 2. RAPORTAREA.
 *
 * Blocarea rezolvă problema unui singur om: nu-l mai vezi. Raportarea e pentru
 * cazul în care problema priveşte pe toată lumea şi trebuie să ajungă la cineva
 * care poate lua o măsură. Fără ea, singura reacţie posibilă la un abuz e să
 * taci şi să blochezi — ceea ce lasă contul liber să facă acelaşi lucru altcuiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tixello_friendships')) {
            Schema::table('tixello_friendships', function (Blueprint $table) {
                $table->string('source', 20)->default('manual')->change();
            });
        }

        if (Schema::hasTable('tixello_friend_invites')) {
            Schema::table('tixello_friend_invites', function (Blueprint $table) {
                $table->string('source', 20)->default('invite_code')->change();
            });
        }

        Schema::create('tixello_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_id')->constrained('tixello_accounts')->cascadeOnDelete();

            /* Ce se raportează. Azi doar conturi; mâine şi un short sau o
               recenzie — de aceea subiectul e polimorf de la început, nu o
               coloană `reported_account_id` care ar trebui înlocuită. */
            $table->string('subject_type', 40)->default('account');
            $table->unsignedBigInteger('subject_id');

            $table->string('reason', 40);
            $table->text('note')->nullable();

            /* `open` până se uită cineva peste el. Fără stare, un raport citit
               şi unul necitit arată la fel, iar moderarea devine ghicit. */
            $table->string('status', 20)->default('open');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            /* Un singur raport deschis per (reclamant, subiect): al doilea tap
               pe „Raportează" nu trebuie să umple coada cu duplicate. */
            $table->unique(['reporter_id', 'subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_reports');
    }
};
