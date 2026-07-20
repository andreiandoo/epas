<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // Manual "sold out" flag toggled from the admin per ticket type.
            // Purely interface/purchase gating — does NOT touch stock
            // (quota_total/quota_sold). When true the type renders as sold out
            // on the storefront and is rejected at checkout.
            $table->boolean('is_sold_out')->default(false)->after('is_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('is_sold_out');
        });
    }
};
