<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artist_booking_listings', function (Blueprint $table) {
            $table->string('ical_token', 64)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('artist_booking_listings', function (Blueprint $table) {
            $table->dropColumn('ical_token');
        });
    }
};
