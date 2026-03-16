<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_template_customizations')) {
            if (!Schema::hasColumn('web_template_customizations', 'preview_password')) {
                Schema::table('web_template_customizations', function (Blueprint $table) {
                    $table->string('preview_password')->nullable()->after('demo_data_overrides');
                    $table->json('utm_data')->nullable()->after('last_viewed_at');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('web_template_customizations')) {
            Schema::table('web_template_customizations', function (Blueprint $table) {
                $table->dropColumn(['preview_password', 'utm_data']);
            });
        }
    }
};
