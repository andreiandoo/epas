<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('tour_id')
                ->constrained('seasons')->nullOnDelete();
            $table->foreignId('repertoire_id')->nullable()->after('season_id')
                ->constrained('repertoire')->nullOnDelete();

            $table->index('season_id');
            $table->index('repertoire_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropForeign(['repertoire_id']);
            $table->dropIndex(['season_id']);
            $table->dropIndex(['repertoire_id']);
            $table->dropColumn(['season_id', 'repertoire_id']);
        });
    }
};
