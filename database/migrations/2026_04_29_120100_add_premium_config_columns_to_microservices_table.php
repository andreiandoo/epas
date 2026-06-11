<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            if (!Schema::hasColumn('microservices', 'version')) {
                $table->string('version', 20)->nullable()->after('category');
            }
            if (!Schema::hasColumn('microservices', 'is_premium')) {
                $table->boolean('is_premium')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('microservices', 'config_schema')) {
                $table->json('config_schema')->nullable();
            }
            if (!Schema::hasColumn('microservices', 'required_env_vars')) {
                $table->json('required_env_vars')->nullable();
            }
            if (!Schema::hasColumn('microservices', 'dependencies')) {
                $table->json('dependencies')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            foreach (['version', 'is_premium', 'config_schema', 'required_env_vars', 'dependencies'] as $col) {
                if (Schema::hasColumn('microservices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
