<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->text('first_referrer')->nullable()->change();
            $table->text('first_landing_page')->nullable()->change();
            $table->text('last_referrer')->nullable()->change();
            $table->text('last_landing_page')->nullable()->change();
            $table->string('first_fbclid', 500)->nullable()->change();
            $table->string('last_fbclid', 500)->nullable()->change();
            $table->string('first_campaign', 500)->nullable()->change();
            $table->string('last_campaign', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->string('first_referrer', 255)->nullable()->change();
            $table->string('first_landing_page', 255)->nullable()->change();
            $table->string('last_referrer', 255)->nullable()->change();
            $table->string('last_landing_page', 255)->nullable()->change();
            $table->string('first_fbclid', 255)->nullable()->change();
            $table->string('last_fbclid', 255)->nullable()->change();
            $table->string('first_campaign', 255)->nullable()->change();
            $table->string('last_campaign', 255)->nullable()->change();
        });
    }
};
