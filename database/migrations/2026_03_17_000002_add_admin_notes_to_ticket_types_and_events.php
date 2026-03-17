<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('description');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('seo');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });
    }
};
