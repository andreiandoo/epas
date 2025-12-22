<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Make tenant_id nullable for global taxes managed in Core admin
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
        });

        Schema::table('local_taxes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });

        Schema::table('local_taxes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });
    }
};
