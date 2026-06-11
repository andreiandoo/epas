<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->text('page_url')->nullable()->change();
            $table->text('referrer')->nullable()->change();
            $table->string('fbclid', 500)->nullable()->change();
            $table->string('page_path', 500)->nullable()->change();
            $table->string('page_title', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->string('page_url', 255)->nullable()->change();
            $table->string('referrer', 255)->nullable()->change();
            $table->string('fbclid', 255)->nullable()->change();
            $table->string('page_path', 255)->nullable()->change();
            $table->string('page_title', 255)->nullable()->change();
        });
    }
};
