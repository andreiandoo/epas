<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->string('id_card_document')->nullable()->after('logo');
            $table->string('cui_document')->nullable()->after('id_card_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['id_card_document', 'cui_document']);
        });
    }
};
