<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('wristbands', 'pin_hash')) {
            Schema::table('wristbands', function (Blueprint $table) {
                $table->string('pin_hash', 64)->nullable()->after('currency');
                $table->string('qr_payload')->nullable()->after('pin_hash');
                $table->timestamp('last_transaction_at')->nullable()->after('qr_payload');
                $table->string('last_pos_device_uid')->nullable()->after('last_transaction_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('wristbands', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'qr_payload', 'last_transaction_at', 'last_pos_device_uid']);
        });
    }
};
