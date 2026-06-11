<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'issuing_tax_registry_id')) {
                $table->unsignedBigInteger('issuing_tax_registry_id')->nullable()->after('requires_vehicle_info');
                $table->index('issuing_tax_registry_id', 'tt_issuing_tax_registry_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'issuing_tax_registry_id')) {
                $table->dropIndex('tt_issuing_tax_registry_idx');
                $table->dropColumn('issuing_tax_registry_id');
            }
        });
    }
};
