<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a ticket type's is_sold_out flag was turned on by the
 * event-level "Sold Out" cascade (true) vs. set individually by an operator
 * (false). Lets un-marking the event restore only the types the cascade turned
 * on, leaving individually sold-out types alone.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('sold_out_by_event')->default(false)->after('is_sold_out');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('sold_out_by_event');
        });
    }
};
