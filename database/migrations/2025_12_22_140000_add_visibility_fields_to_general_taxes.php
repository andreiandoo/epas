<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->boolean('visible_on_checkout')->default(false)->after('is_active');
            $table->boolean('visible_on_ticket')->default(false)->after('visible_on_checkout');
        });
    }

    public function down(): void
    {
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->dropColumn(['visible_on_checkout', 'visible_on_ticket']);
        });
    }
};
