<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_integrations', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->after('tenant_id');
            $table->foreign('marketplace_organizer_id')
                ->references('id')
                ->on('marketplace_organizers')
                ->nullOnDelete();
            $table->index('marketplace_organizer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_integrations', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropColumn('marketplace_organizer_id');
        });
    }
};
