<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poza de profil a clientului.
 *
 * Se pastreaza CALEA pe discul public, nu un URL absolut: domeniul se poate
 * schimba (si s-a schimbat deja, intre marketplace-uri), iar un URL scris in
 * baza ar ramane fix si ar da 404 dupa mutare.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'avatar_path')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('avatar_path', 255)->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'avatar_path')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('avatar_path');
            });
        }
    }
};
