<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->after('marketplace_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->dropColumn('marketplace_organizer_id');
        });
    }
};
