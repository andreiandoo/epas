<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'service_category')) {
                $table->string('service_category', 32)->nullable()->after('issuing_tax_registry_id');
                $table->index('service_category', 'tt_service_category_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'service_category')) {
                $table->dropIndex('tt_service_category_idx');
                $table->dropColumn('service_category');
            }
        });
    }
};
