<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->unsignedSmallInteger('founded_year')->nullable()->after('country');
            $table->unsignedSmallInteger('members_count')->nullable()->after('founded_year');
            $table->string('record_label', 255)->nullable()->after('members_count');
            $table->json('achievements')->nullable()->after('record_label');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['founded_year', 'members_count', 'record_label', 'achievements']);
        });
    }
};
