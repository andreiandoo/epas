<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletter signups (POST /customer/newsletter/subscribe) cer doar email +
 * acceptarea consimțământului — fără nume. Coloanele first_name și last_name
 * erau NOT NULL în schema originală, ceea ce arunca 23502 la insert pentru
 * orice abonare la newsletter fără date de contact complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        // În down() nu putem face safely NOT NULL fără default, pentru că între
        // timp pot exista rânduri cu NULL. Setăm un default empty string.
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->string('first_name')->default('')->nullable(false)->change();
            $table->string('last_name')->default('')->nullable(false)->change();
        });
    }
};
