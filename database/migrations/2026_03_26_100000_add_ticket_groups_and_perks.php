<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ticket type grouping and perks
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->string('ticket_group', 120)->nullable()->after('sort_order');
            $table->json('perks')->nullable()->after('ticket_group');
        });

        // Event-level toggles
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('enable_ticket_groups')->default(false);
            $table->boolean('enable_ticket_perks')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['ticket_group', 'perks']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['enable_ticket_groups', 'enable_ticket_perks']);
        });
    }
};
