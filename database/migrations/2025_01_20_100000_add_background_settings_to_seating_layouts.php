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
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->decimal('background_scale', 5, 2)->default(1.00)->after('background_image_url');
            $table->integer('background_x')->default(0)->after('background_scale');
            $table->integer('background_y')->default(0)->after('background_x');
            $table->decimal('background_opacity', 3, 2)->default(0.30)->after('background_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropColumn(['background_scale', 'background_x', 'background_y', 'background_opacity']);
        });
    }
};
