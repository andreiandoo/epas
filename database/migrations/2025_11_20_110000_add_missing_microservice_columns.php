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
        Schema::table('microservices', function (Blueprint $table) {
            if (!Schema::hasColumn('microservices', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status');
            }
            if (!Schema::hasColumn('microservices', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
            if (!Schema::hasColumn('microservices', 'short_description')) {
                $table->string('short_description')->nullable()->after('description');
            }
            if (!Schema::hasColumn('microservices', 'icon_image')) {
                $table->string('icon_image')->nullable()->after('icon');
            }
            if (!Schema::hasColumn('microservices', 'public_image')) {
                $table->string('public_image')->nullable()->after('icon_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'is_active', 'short_description', 'icon_image', 'public_image']);
        });
    }
};
