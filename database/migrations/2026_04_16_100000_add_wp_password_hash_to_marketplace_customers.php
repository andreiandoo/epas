<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->string('wp_password_hash', 255)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->dropColumn('wp_password_hash');
        });
    }
};
