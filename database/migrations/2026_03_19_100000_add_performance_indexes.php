<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->index('domain');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('slug');
            $table->index('venue_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['domain']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['venue_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });
    }
};
