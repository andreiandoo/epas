<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Graful de prietenie al aplicaţiei Tixello.
 *
 * DE CE PESTE `tixello_accounts`, nu peste `customers`
 * Un client de pe ambilet.ro şi unul de pe bilete.online sunt, în baza de date,
 * doi oameni care nu se cunosc — sunt rânduri în tabele diferite, ale unor lumi
 * separate. Construit acolo, graful s-ar fi rupt pe marketplace-uri: aceiaşi doi
 * prieteni ar fi fost prieteni pe unul şi străini pe celălalt. `tixello_accounts`
 * e singura identitate care traversează tot sistemul.
 *
 * RECIPROCITATE, cu un singur rând per pereche
 * Prietenia e reciprocă, dar rândul reţine cine a cerut şi cine a răspuns —
 * altfel n-am mai putea afişa cererile primite şi n-am putea distinge un refuz
 * de o cerere care încă aşteaptă. Perechea se păstrează CANONIC (id mic, id
 * mare) ca A→B şi B→A să nu poată produce două rânduri; cine a fost iniţiatorul
 * rămâne în `requested_by`.
 *
 * NIMIC NU DEVINE PRIETENIE AUTOMAT
 * Nici codul de invitaţie, nici adăugarea cuiva ca beneficiar la checkout.
 * Ambele deschid o CERERE, pe care celălalt o acceptă. Un beneficiar poate fi
 * un coleg căruia i-ai luat bilet o dată; a-l face prieten fără să fie întrebat
 * i-ar expune deplasările unui om cu care n-a acceptat nimic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_friendships', function (Blueprint $table) {
            $table->id();

            /* Perechea, canonic: account_a_id < account_b_id, mereu. */
            $table->foreignId('account_a_id')->constrained('tixello_accounts')->cascadeOnDelete();
            $table->foreignId('account_b_id')->constrained('tixello_accounts')->cascadeOnDelete();

            /** Cine a iniţiat — pentru „cereri primite" vs „cereri trimise". */
            $table->foreignId('requested_by')->constrained('tixello_accounts')->cascadeOnDelete();

            $table->enum('status', ['pending', 'accepted', 'declined', 'blocked'])->default('pending');

            /** Cum a apărut cererea. Util la suport şi la măsurarea invitaţiilor. */
            $table->enum('source', ['invite_code', 'beneficiary', 'manual'])->default('manual');

            /** Cine a blocat, când status = blocked: blocarea e unidirecţională. */
            $table->foreignId('blocked_by')->nullable()->constrained('tixello_accounts')->nullOnDelete();

            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['account_a_id', 'account_b_id']);
            $table->index(['account_b_id', 'status']);
            $table->index(['status', 'updated_at']);
        });

        /**
         * Invitaţii către oameni care încă n-au cont.
         *
         * Un cod de invitaţie trimis prin WhatsApp sau un beneficiar adăugat la
         * checkout ajung, de cele mai multe ori, la cineva care nu e încă în
         * Tixello. Fără tabelul ăsta, invitaţia s-ar pierde şi omul ar ajunge în
         * aplicaţie fără să ştie cine l-a chemat.
         *
         * Emailul e stocat NORMALIZAT (lowercase, fără spaţii) — altfel
         * „Ion@X.ro" şi „ion@x.ro" ar fi doi oameni diferiţi la conversie.
         */
        Schema::create('tixello_friend_invites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inviter_id')->constrained('tixello_accounts')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();

            $table->enum('source', ['invite_code', 'beneficiary'])->default('invite_code');

            /** Se completează când destinatarul îşi face cont şi cererea se materializează. */
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_account_id')->nullable()
                ->constrained('tixello_accounts')->nullOnDelete();

            $table->timestamps();

            /* O singură invitaţie în aşteptare per (invitator, email): a doua
               oară când adaugi acelaşi beneficiar nu se creează încă un rând. */
            $table->unique(['inviter_id', 'email']);
            $table->index('email');
        });

        /**
         * Vizibilitatea participării, per eveniment.
         *
         * Regula generală stă pe cont (`tixello_accounts.friends_visibility`) şi
         * e „nimeni" implicit. Aici se scriu doar EXCEPŢIILE — un eveniment la
         * care vrei (sau nu vrei) să se ştie că mergi. Un rând per excepţie, nu
         * per eveniment la care ai bilet: majoritatea urmează regula generală şi
         * n-au ce căuta în tabel.
         */
        Schema::create('tixello_event_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tixello_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->boolean('visible');
            $table->timestamps();

            $table->unique(['tixello_account_id', 'event_id']);
            $table->index('event_id');
        });

        Schema::table('tixello_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('tixello_accounts', 'invite_code')) {
                /* Codul propriu, cu care inviţi. Scurt, ca să poată fi dictat la
                   telefon; unic, ca linkul să nu ducă la altcineva. */
                $table->string('invite_code', 12)->nullable()->unique()->after('phone');
            }

            if (! Schema::hasColumn('tixello_accounts', 'friends_visibility')) {
                /* Implicit „nobody": biletele spun unde eşti, la ce oră şi cu
                   cine. Vizibil-din-oficiu ar publica deplasările oamenilor fără
                   să-i întrebe nimeni. */
                $table->enum('friends_visibility', ['nobody', 'friends'])->default('nobody')->after('locale');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_event_visibility');
        Schema::dropIfExists('tixello_friend_invites');
        Schema::dropIfExists('tixello_friendships');

        Schema::table('tixello_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tixello_accounts', 'invite_code')) {
                $table->dropColumn('invite_code');
            }

            if (Schema::hasColumn('tixello_accounts', 'friends_visibility')) {
                $table->dropColumn('friends_visibility');
            }
        });
    }
};
