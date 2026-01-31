<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('letter', 1)->nullable()->after('name')->index();
        });

        // Backfill existing artists with their first letter (uppercase)
        DB::statement("UPDATE artists SET letter = UPPER(SUBSTRING(name, 1, 1)) WHERE name IS NOT NULL AND name != ''");
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn('letter');
        });
    }
};
