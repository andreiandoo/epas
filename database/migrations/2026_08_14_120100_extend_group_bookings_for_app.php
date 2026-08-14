<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Cumpărăm împreună" — deschide `group_bookings` către aplicație.
 *
 * Tabelele existau, dar erau folosite doar din panoul organizatorului: grupul
 * se crea de un om, membrii erau nume și telefoane, iar plata se urmărea
 * manual. Din aplicație, grupul îl face CUMPĂRĂTORUL, iar membrii sunt conturi
 * tics — prieteni, nu rânduri într-un tabel.
 *
 * REZERVAREA DE STOC
 * `hold_expires_at` e momentul până la care ținem locurile. Regula, hotărâtă
 * împreună cu produsul: 48 de ore, dar fereastra NU intră în ziua evenimentului
 * — biletele trebuie luate cu cel puțin o zi înainte. Un grup care n-a plătit
 * până atunci eliberează stocul; altfel am fi ajuns să ținem locuri până în
 * seara concertului și să le pierdem pe amândouă (și vânzarea, și grupul).
 *
 * PLATA
 * Fiecare membru plătește la PROCESATORUL ORGANIZATORULUI, prin checkout-ul
 * obișnuit — de aceea `order_id` pe membru. Nu există un cont-intermediar care
 * încasează și distribuie: procesatorul diferă de la organizator la organizator
 * (Netopia la unii, Stripe la alții), iar un intermediar ne-ar face comerciant
 * de înregistrare, cu tot ce înseamnă asta fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('tixello_account_id')->nullable()->after('organizer_customer_id')->index();
            $table->unsignedBigInteger('ticket_type_id')->nullable()->after('event_id')->index();
            $table->timestamp('hold_expires_at')->nullable()->after('deadline_at')->index();
            /* Codul cu care intri in grup dintr-un link primit pe WhatsApp. */
            $table->string('invite_code', 16)->nullable()->unique();
        });

        Schema::table('group_booking_members', function (Blueprint $table) {
            $table->unsignedBigInteger('tixello_account_id')->nullable()->after('ticket_id')->index();
            /* Comanda proprie a membrului, la procesatorul organizatorului.
               Biletul lui iese din ea, nu dintr-un cos comun. */
            $table->unsignedBigInteger('order_id')->nullable()->after('tixello_account_id')->index();
            $table->string('invite_token', 64)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('group_booking_members', function (Blueprint $table) {
            $table->dropColumn(['tixello_account_id', 'order_id', 'invite_token', 'invited_at']);
        });

        Schema::table('group_bookings', function (Blueprint $table) {
            $table->dropColumn(['tixello_account_id', 'ticket_type_id', 'hold_expires_at', 'invite_code']);
        });
    }
};
