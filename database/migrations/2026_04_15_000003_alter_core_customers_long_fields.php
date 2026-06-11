<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            // Only alter columns that exist
            $columns = Schema::getColumnListing('core_customers');
            if (in_array('first_referrer', $columns)) $table->text('first_referrer')->nullable()->change();
            if (in_array('first_landing_page', $columns)) $table->text('first_landing_page')->nullable()->change();
            if (in_array('last_referrer', $columns)) $table->text('last_referrer')->nullable()->change();
            if (in_array('last_landing_page', $columns)) $table->text('last_landing_page')->nullable()->change();
            if (in_array('first_fbclid', $columns)) $table->string('first_fbclid', 500)->nullable()->change();
            if (in_array('last_fbclid', $columns)) $table->string('last_fbclid', 500)->nullable()->change();
            if (in_array('first_campaign', $columns)) $table->string('first_campaign', 500)->nullable()->change();
            if (in_array('last_campaign', $columns)) $table->string('last_campaign', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // No-op: don't shrink columns back
    }
};
