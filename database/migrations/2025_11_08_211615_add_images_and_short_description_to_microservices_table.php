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
        Schema::table('microservices', function (Blueprint $table) {
            $table->string('icon_image')->nullable()->after('icon')->comment('Icon image for UI cards');
            $table->string('public_image')->nullable()->after('icon_image')->comment('Image for public pages');
            $table->text('short_description')->nullable()->after('description')->comment('Brief description for cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            $table->dropColumn(['icon_image', 'public_image', 'short_description']);
        });
    }
};
