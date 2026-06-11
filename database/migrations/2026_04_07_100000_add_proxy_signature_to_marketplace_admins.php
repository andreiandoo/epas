<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_admins', function (Blueprint $table) {
            $table->string('proxy_signature_image')->nullable()->after('proxy_authorization_file');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_admins', function (Blueprint $table) {
            $table->dropColumn('proxy_signature_image');
        });
    }
};
