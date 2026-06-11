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
            // Add missing columns that seeders expect
            if (!Schema::hasColumn('microservices', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('pricing_model');
            }
            if (!Schema::hasColumn('microservices', 'category')) {
                $table->string('category')->nullable()->after('features');
            }
            if (!Schema::hasColumn('microservices', 'currency')) {
                $table->string('currency', 3)->default('EUR')->after('price');
            }
            if (!Schema::hasColumn('microservices', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('microservices', 'documentation_url')) {
                $table->string('documentation_url')->nullable()->after('icon');
            }
            if (!Schema::hasColumn('microservices', 'metadata')) {
                $table->json('metadata')->nullable()->after('documentation_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            $columns = ['is_active', 'category', 'currency', 'billing_cycle', 'documentation_url', 'metadata'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('microservices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
