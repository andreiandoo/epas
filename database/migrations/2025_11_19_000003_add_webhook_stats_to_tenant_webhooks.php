<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_webhooks', function (Blueprint $table) {
            $table->unsignedInteger('successful_deliveries')->default(0)->after('metadata');
            $table->unsignedInteger('failed_deliveries')->default(0)->after('successful_deliveries');
            $table->timestamp('last_successful_delivery_at')->nullable()->after('failed_deliveries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_webhooks', function (Blueprint $table) {
            $table->dropColumn(['successful_deliveries', 'failed_deliveries', 'last_successful_delivery_at']);
        });
    }
};
