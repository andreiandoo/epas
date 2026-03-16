<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('is_published');
            $table->string('suggested_venue_name', 255)->nullable()->after('venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'suggested_venue_name']);
        });
    }
};
