<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_customer_events')) {
            return;
        }

        if (!Schema::hasColumn('core_customer_events', 'conversion_value')) {
            Schema::table('core_customer_events', function (Blueprint $table) {
                $table->decimal('conversion_value', 12, 2)->nullable()->after('event_value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('core_customer_events') && Schema::hasColumn('core_customer_events', 'conversion_value')) {
            Schema::table('core_customer_events', function (Blueprint $table) {
                $table->dropColumn('conversion_value');
            });
        }
    }
};
