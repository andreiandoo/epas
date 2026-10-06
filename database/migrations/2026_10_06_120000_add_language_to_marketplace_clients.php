<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The marketplace API reads $client->language (falling back to 'ro') to pick the language of names and texts,
 * but the column never existed, so every marketplace was served in Romanian. Null keeps that behaviour for the
 * existing marketplaces; an English marketplace (Viaqui) sets 'en'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_clients', 'language')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->string('language', 5)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_clients', 'language')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};
