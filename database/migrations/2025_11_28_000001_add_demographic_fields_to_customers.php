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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('city', 255)->nullable()->after('phone');
            $table->string('country', 255)->nullable()->after('city');
            $table->date('date_of_birth')->nullable()->after('country');
            $table->integer('age')->unsigned()->nullable()->after('date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['city', 'country', 'date_of_birth', 'age']);
        });
    }
};
