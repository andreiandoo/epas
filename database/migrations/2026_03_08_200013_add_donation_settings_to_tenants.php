<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('donations_enabled')->default(false)->after('type_settings')
                ->comment('Allow voluntary donations at checkout');
            $table->json('donation_settings')->nullable()->after('donations_enabled')
                ->comment('{"suggested_amounts":[5,10,20],"custom_amount":true,"label":{"ro":"Doresc sa donez","en":"I want to donate"},"description":{"ro":"...","en":"..."}}');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['donations_enabled', 'donation_settings']);
        });
    }
};
