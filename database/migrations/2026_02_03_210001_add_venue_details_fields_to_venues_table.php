<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('has_historical_monument_tax');
            $table->string('open_hours', 255)->nullable()->after('timezone');
            $table->text('general_rules')->nullable()->after('open_hours');
            $table->text('child_rules')->nullable()->after('general_rules');
            $table->text('accepted_payment')->nullable()->after('child_rules');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'open_hours',
                'general_rules',
                'child_rules',
                'accepted_payment',
            ]);
        });
    }
};
