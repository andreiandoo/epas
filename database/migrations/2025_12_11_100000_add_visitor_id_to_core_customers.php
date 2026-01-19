<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('core_customers', 'visitor_id')) {
                $table->string('visitor_id', 64)->nullable()->index()->after('uuid')
                    ->comment('Anonymous visitor ID for linking pre-identification sessions');
            }
            if (!Schema::hasColumn('core_customers', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('city');
            }
            if (!Schema::hasColumn('core_customers', 'device_type')) {
                $table->string('device_type', 20)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('core_customers', 'browser')) {
                $table->string('browser')->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('core_customers', 'os')) {
                $table->string('os')->nullable()->after('browser');
            }
            if (!Schema::hasColumn('core_customers', 'has_cart_abandoned')) {
                $table->boolean('has_cart_abandoned')->default(false)->after('churn_risk_score');
            }
            if (!Schema::hasColumn('core_customers', 'last_cart_abandoned_at')) {
                $table->timestamp('last_cart_abandoned_at')->nullable()->after('has_cart_abandoned');
            }
            if (!Schema::hasColumn('core_customers', 'rfm_score')) {
                $table->integer('rfm_score')->nullable()->after('rfm_segment')
                    ->comment('Combined RFM score for quick filtering');
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $columns = ['visitor_id', 'ip_address', 'device_type', 'browser', 'os',
                        'has_cart_abandoned', 'last_cart_abandoned_at', 'rfm_score'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('core_customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
