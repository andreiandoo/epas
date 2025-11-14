<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['event_categories', 'event_genres', 'music_genres', 'event_tags'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->unsignedBigInteger('parent_id')->nullable()->index()->after('id');
                // FK opțional (atenție la seed order)
                // $t->foreign('parent_id')->references('id')->on($table)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['event_categories', 'event_genres', 'music_genres', 'event_tags'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (Schema::hasColumn($t->getTable(), 'parent_id')) {
                    $t->dropColumn('parent_id');
                }
            });
        }
    }
};
