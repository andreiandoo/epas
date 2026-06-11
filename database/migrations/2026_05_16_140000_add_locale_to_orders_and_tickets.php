<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adauga `locale` nullable pe `orders` si `tickets` pentru a stoca limba aleasa
 * de client la momentul comenzii (RO/EN/HU/etc.).
 *
 * Valoarea NULL = "fara preferinta explicita" → toate pipeline-urile actuale
 * vor folosi fallback la 'ro' (sau locale-ul default al organizatorului), deci
 * comenzile existente raman neafectate.
 *
 * Folosit de pipeline-ul de generare biletelor PDF + emailuri tranzactionale
 * (Faza A — multi-limba pentru leisure_venue Sf. Ana).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: skip daca a fost rulata deja partial.
        if (!Schema::hasColumn('orders', 'locale')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('locale', 8)->nullable();
                $table->index('locale');
            });
        }

        if (!Schema::hasColumn('tickets', 'locale')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('locale', 8)->nullable();
                $table->index('locale');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });
    }
};
