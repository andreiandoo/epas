<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->string('background_color', 7)->default('#F3F4F6')->after('background_opacity');
        });
    }

    public function down(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropColumn('background_color');
        });
    }
};
