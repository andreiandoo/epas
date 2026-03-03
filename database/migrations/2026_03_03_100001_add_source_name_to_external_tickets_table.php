<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_tickets', function (Blueprint $table) {
            $table->string('source_name', 255)->nullable()->after('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('external_tickets', function (Blueprint $table) {
            $table->dropColumn('source_name');
        });
    }
};
