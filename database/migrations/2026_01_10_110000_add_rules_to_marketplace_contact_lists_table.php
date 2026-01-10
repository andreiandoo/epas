<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_contact_lists', function (Blueprint $table) {
            $table->enum('list_type', ['manual', 'dynamic'])->default('manual')->after('description');
            $table->json('rules')->nullable()->after('list_type');
            $table->timestamp('last_synced_at')->nullable()->after('rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_contact_lists', function (Blueprint $table) {
            $table->dropColumn(['list_type', 'rules', 'last_synced_at']);
        });
    }
};
