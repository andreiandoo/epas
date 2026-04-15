<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_sessions', function (Blueprint $table) {
            $table->text('landing_page')->nullable()->change();
            $table->text('referrer')->nullable()->change();
            $table->string('fbclid', 500)->nullable()->change();
            $table->string('campaign', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('core_sessions', function (Blueprint $table) {
            $table->string('landing_page', 255)->nullable()->change();
            $table->string('referrer', 255)->nullable()->change();
            $table->string('fbclid', 255)->nullable()->change();
            $table->string('campaign', 255)->nullable()->change();
        });
    }
};
