<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->string('visitor_id', 64)->nullable()->index()->after('uuid')
                ->comment('Anonymous visitor ID for linking pre-identification sessions');
            $table->string('ip_address', 45)->nullable()->after('city');
            $table->string('device_type', 20)->nullable()->after('ip_address');
            $table->string('browser')->nullable()->after('device_type');
            $table->string('os')->nullable()->after('browser');
            $table->boolean('has_cart_abandoned')->default(false)->after('churn_risk_score');
            $table->timestamp('last_cart_abandoned_at')->nullable()->after('has_cart_abandoned');
            $table->integer('rfm_score')->nullable()->after('rfm_segment')
                ->comment('Combined RFM score for quick filtering');
        });
    }

    public function down(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->dropColumn([
                'visitor_id',
                'ip_address',
                'device_type',
                'browser',
                'os',
                'has_cart_abandoned',
                'last_cart_abandoned_at',
                'rfm_score',
            ]);
        });
    }
};
