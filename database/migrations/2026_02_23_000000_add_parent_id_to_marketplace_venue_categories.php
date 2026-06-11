<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_venue_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('marketplace_client_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('marketplace_venue_categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_venue_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
