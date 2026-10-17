<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evenimente ale căror bilete se vând în altă parte (ex. concertul unui artist la un
 * festival): site-ul tenantului arată data și trimite la linkul extern, fără coș.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'external_ticket_url')) {
                $table->string('external_ticket_url', 500)->nullable();
            }
            if (! Schema::hasColumn('events', 'external_ticket_label')) {
                $table->string('external_ticket_label', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['external_ticket_url', 'external_ticket_label'] as $c) {
                if (Schema::hasColumn('events', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
