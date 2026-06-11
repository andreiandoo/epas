<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campuri suplimentare pentru tipuri de bilete leisure_venue (servicii).
 *
 * - service_duration_minutes: durata serviciului (ex: parcare 60 min, kayak 120 min).
 *   Vizibil doar pentru service_category in [parking, rental].
 * - product_description: descriere WYSIWYG produs (HTML), pentru pagina publica.
 * - usage_terms: conditii de utilizare / termeni (HTML).
 * - requires_access_ticket: cand TRUE, serviciul poate fi cumparat doar daca
 *   acelasi order contine si un bilet acces valid pentru aceeasi zi.
 *
 * Toate sunt aditive nullable / default false. Zero impact pe ticketele existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'service_duration_minutes')) {
                $table->unsignedInteger('service_duration_minutes')->nullable()->after('service_category');
            }
            if (!Schema::hasColumn('ticket_types', 'product_description')) {
                $table->text('product_description')->nullable()->after('service_duration_minutes');
            }
            if (!Schema::hasColumn('ticket_types', 'usage_terms')) {
                $table->text('usage_terms')->nullable()->after('product_description');
            }
            if (!Schema::hasColumn('ticket_types', 'requires_access_ticket')) {
                $table->boolean('requires_access_ticket')->default(false)->after('usage_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            foreach (['requires_access_ticket', 'usage_terms', 'product_description', 'service_duration_minutes'] as $c) {
                if (Schema::hasColumn('ticket_types', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
