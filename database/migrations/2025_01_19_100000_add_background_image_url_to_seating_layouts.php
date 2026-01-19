<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->string('background_image_url', 500)->nullable()->after('background_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropColumn('background_image_url');
        });
    }
};
