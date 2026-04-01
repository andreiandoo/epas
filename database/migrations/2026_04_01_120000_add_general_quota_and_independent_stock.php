<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('general_quota')->nullable()->after('general_stock');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('is_independent_stock')->default(false)->after('quota_sold');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('general_quota');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('is_independent_stock');
        });
    }
};
