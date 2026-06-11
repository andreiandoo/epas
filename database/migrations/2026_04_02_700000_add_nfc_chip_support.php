<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nfc_chip_type to festival_editions
        Schema::table('festival_editions', function (Blueprint $table) {
            $table->string('nfc_chip_type', 20)->default('desfire_ev3')->after('cashless_mode');
        });

        // 2. Add NFC-specific fields to cashless_settings
        Schema::table('cashless_settings', function (Blueprint $table) {
            // NFC chip type (mirrors edition for quick access)
            $table->string('nfc_chip_type', 20)->default('desfire_ev3')->after('meta');

            // DESFire EV3 specific
            $table->string('desfire_app_id', 6)->default('010203')->after('nfc_chip_type');
            $table->text('desfire_key_master')->nullable()->after('desfire_app_id');
            $table->text('desfire_key_topup')->nullable()->after('desfire_key_master');
            $table->text('desfire_key_pos')->nullable()->after('desfire_key_topup');
            $table->text('desfire_key_readonly')->nullable()->after('desfire_key_pos');
            $table->integer('desfire_value_upper_limit')->default(5000000)->after('desfire_key_readonly');
            $table->timestamp('desfire_keys_rotated_at')->nullable()->after('desfire_value_upper_limit');

            // NTAG213 specific
            $table->string('ntag_password', 8)->nullable()->after('desfire_keys_rotated_at');
            $table->string('ntag_ndef_url_prefix')->nullable()->after('ntag_password');
        });

        // 3. NFC key management table (DESFire keys per edition)
        Schema::create('cashless_nfc_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('key_slot', 20); // master, topup, pos, readonly
            $table->text('encrypted_key'); // AES key encrypted with app master secret
            $table->integer('key_version')->default(1);
            $table->timestamp('rotated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['festival_edition_id', 'key_slot'], 'nfc_key_edition_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashless_nfc_keys');

        Schema::table('cashless_settings', function (Blueprint $table) {
            $table->dropColumn([
                'nfc_chip_type', 'desfire_app_id', 'desfire_key_master', 'desfire_key_topup',
                'desfire_key_pos', 'desfire_key_readonly', 'desfire_value_upper_limit',
                'desfire_keys_rotated_at', 'ntag_password', 'ntag_ndef_url_prefix',
            ]);
        });

        Schema::table('festival_editions', function (Blueprint $table) {
            $table->dropColumn('nfc_chip_type');
        });
    }
};
