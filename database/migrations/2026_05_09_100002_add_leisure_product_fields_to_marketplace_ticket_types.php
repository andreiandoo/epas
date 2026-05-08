<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'service_duration_minutes')) {
                $table->unsignedInteger('service_duration_minutes')->nullable()->after('service_category');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'product_description')) {
                $table->text('product_description')->nullable()->after('service_duration_minutes');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'usage_terms')) {
                $table->text('usage_terms')->nullable()->after('product_description');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'requires_access_ticket')) {
                $table->boolean('requires_access_ticket')->default(false)->after('usage_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            foreach (['requires_access_ticket', 'usage_terms', 'product_description', 'service_duration_minutes'] as $c) {
                if (Schema::hasColumn('marketplace_ticket_types', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
