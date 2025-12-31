<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_seating_layouts', function (Blueprint $table) {
            // Add status column
            $table->string('status', 20)->default('draft')->after('json_geometry');

            // Add archived_at timestamp
            $table->timestamp('archived_at')->nullable()->after('published_at');

            // Add index for status queries
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_seating_layouts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'archived_at']);
        });
    }
};
