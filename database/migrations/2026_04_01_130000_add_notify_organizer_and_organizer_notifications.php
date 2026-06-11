<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_email_templates', function (Blueprint $table) {
            $table->boolean('notify_organizer')->default(false)->after('is_active');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->jsonb('organizer_notifications')->nullable()->after('general_quota');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_email_templates', function (Blueprint $table) {
            $table->dropColumn('notify_organizer');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('organizer_notifications');
        });
    }
};
