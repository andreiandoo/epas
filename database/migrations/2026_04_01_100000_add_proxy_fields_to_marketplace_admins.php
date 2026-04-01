<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_admins', function (Blueprint $table) {
            $table->string('proxy_full_name')->nullable()->after('timezone');
            $table->string('proxy_role')->nullable()->after('proxy_full_name');
            $table->string('proxy_address')->nullable()->after('proxy_role');
            $table->string('proxy_country')->nullable()->after('proxy_address');
            $table->string('proxy_county')->nullable()->after('proxy_country');
            $table->string('proxy_city')->nullable()->after('proxy_county');
            $table->string('proxy_id_series')->nullable()->after('proxy_city');
            $table->string('proxy_id_number')->nullable()->after('proxy_id_series');
            $table->string('proxy_cnp')->nullable()->after('proxy_id_number');
            $table->string('proxy_phone')->nullable()->after('proxy_cnp');
            $table->string('proxy_id_card_file')->nullable()->after('proxy_phone');
            $table->string('proxy_authorization_file')->nullable()->after('proxy_id_card_file');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_admins', function (Blueprint $table) {
            $table->dropColumn([
                'proxy_full_name', 'proxy_role', 'proxy_address',
                'proxy_country', 'proxy_county', 'proxy_city',
                'proxy_id_series', 'proxy_id_number', 'proxy_cnp', 'proxy_phone',
                'proxy_id_card_file', 'proxy_authorization_file',
            ]);
        });
    }
};
