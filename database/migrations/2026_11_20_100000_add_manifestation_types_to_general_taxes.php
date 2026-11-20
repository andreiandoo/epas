<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pe ce tip de manifestare (events.manifestation_type) se aplica un timbru:
 * teatrala, muzicala etc. Folosit de declaratia de impozit pe spectacole.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('general_taxes', 'manifestation_types')) {
            return;
        }
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->json('manifestation_types')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('general_taxes', 'manifestation_types')) {
            Schema::table('general_taxes', function (Blueprint $table) {
                $table->dropColumn('manifestation_types');
            });
        }
    }
};
