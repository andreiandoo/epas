<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->string('sync_source', 15)->default('online')->after('meta');
            $table->string('offline_ref')->nullable()->unique()->after('sync_source');
            $table->timestamp('offline_transacted_at')->nullable()->after('offline_ref');
            $table->boolean('is_reconciled')->default(true)->after('offline_transacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->dropColumn(['sync_source', 'offline_ref', 'offline_transacted_at', 'is_reconciled']);
        });
    }
};
