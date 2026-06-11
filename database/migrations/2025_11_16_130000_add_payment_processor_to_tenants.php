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
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('payment_processor', ['stripe', 'netopia', 'euplatesc', 'payu'])
                ->nullable()
                ->after('features');
            $table->enum('payment_processor_mode', ['test', 'live'])
                ->default('test')
                ->after('payment_processor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['payment_processor', 'payment_processor_mode']);
        });
    }
};
