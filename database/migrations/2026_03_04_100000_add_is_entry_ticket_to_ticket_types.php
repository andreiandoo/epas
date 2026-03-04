<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'is_entry_ticket')) {
                $table->boolean('is_entry_ticket')->default(false)->after('is_refundable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'is_entry_ticket')) {
                $table->dropColumn('is_entry_ticket');
            }
        });
    }
};
