<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('open_hours', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('open_hours', 255)->nullable()->change();
        });
    }
};
