<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_types') || Schema::hasColumn('service_types', 'audience')) {
            return;
        }

        Schema::table('service_types', function (Blueprint $table) {
            // organizer -> serviciu cumparat de organizatori (default — toate cele existente)
            // artist    -> serviciu cumparat de conturile artist
            // both      -> ambele audiente
            $table->string('audience', 20)->default('organizer')->after('code');
            $table->index('audience', 'st_audience_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_types') || !Schema::hasColumn('service_types', 'audience')) {
            return;
        }

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropIndex('st_audience_idx');
            $table->dropColumn('audience');
        });
    }
};
