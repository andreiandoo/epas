<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            if (!Schema::hasColumn('venue_types', 'venue_category_id')) {
                $table->foreignId('venue_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('venue_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            if (Schema::hasColumn('venue_types', 'venue_category_id')) {
                $table->dropForeign(['venue_category_id']);
                $table->dropColumn('venue_category_id');
            }
        });
    }
};
