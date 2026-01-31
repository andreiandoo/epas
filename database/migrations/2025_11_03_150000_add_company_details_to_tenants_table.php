<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check which columns need to be added
        $columnsToAdd = [];

        if (!Schema::hasColumn('tenants', 'company_name')) {
            $columnsToAdd[] = 'company_name';
        }
        if (!Schema::hasColumn('tenants', 'cui')) {
            $columnsToAdd[] = 'cui';
        }
        if (!Schema::hasColumn('tenants', 'reg_com')) {
            $columnsToAdd[] = 'reg_com';
        }
        if (!Schema::hasColumn('tenants', 'bank_account')) {
            $columnsToAdd[] = 'bank_account';
        }
        if (!Schema::hasColumn('tenants', 'bank_name')) {
            $columnsToAdd[] = 'bank_name';
        }
        if (!Schema::hasColumn('tenants', 'address')) {
            $columnsToAdd[] = 'address';
        }
        if (!Schema::hasColumn('tenants', 'city')) {
            $columnsToAdd[] = 'city';
        }
        if (!Schema::hasColumn('tenants', 'state')) {
            $columnsToAdd[] = 'state';
        }
        if (!Schema::hasColumn('tenants', 'country')) {
            $columnsToAdd[] = 'country';
        }
        if (!Schema::hasColumn('tenants', 'billing_starts_at')) {
            $columnsToAdd[] = 'billing_starts_at';
        }
        if (!Schema::hasColumn('tenants', 'billing_cycle_days')) {
            $columnsToAdd[] = 'billing_cycle_days';
        }

        if (!empty($columnsToAdd)) {
            Schema::table('tenants', function (Blueprint $table) use ($columnsToAdd) {
                // Company details
                if (in_array('company_name', $columnsToAdd)) {
                    $table->string('company_name')->nullable()->after('name');
                }
                if (in_array('cui', $columnsToAdd)) {
                    $table->string('cui', 50)->nullable()->after('company_name');
                }
                if (in_array('reg_com', $columnsToAdd)) {
                    $table->string('reg_com')->nullable()->after('cui');
                }

                // Banking details
                if (in_array('bank_account', $columnsToAdd)) {
                    $table->string('bank_account')->nullable()->after('reg_com');
                }
                if (in_array('bank_name', $columnsToAdd)) {
                    $table->string('bank_name')->nullable()->after('bank_account');
                }

                // Address details
                if (in_array('address', $columnsToAdd)) {
                    $table->text('address')->nullable()->after('bank_name');
                }
                if (in_array('city', $columnsToAdd)) {
                    $table->string('city')->nullable()->after('address');
                }
                if (in_array('state', $columnsToAdd)) {
                    $table->string('state')->nullable()->after('city');
                }
                if (in_array('country', $columnsToAdd)) {
                    $table->string('country', 2)->nullable()->after('state');
                }

                // Billing details
                if (in_array('billing_starts_at', $columnsToAdd)) {
                    $table->timestamp('billing_starts_at')->nullable()->after('due_at');
                }
                if (in_array('billing_cycle_days', $columnsToAdd)) {
                    $table->integer('billing_cycle_days')->default(30)->after('billing_starts_at');
                }
            });
        }

        // Add indexes if columns exist and indexes don't
        try {
            $indexNames = collect(DB::select("SHOW INDEX FROM tenants"))->pluck('Key_name')->unique()->toArray();

            Schema::table('tenants', function (Blueprint $table) use ($indexNames) {
                if (Schema::hasColumn('tenants', 'cui') && !in_array('tenants_cui_index', $indexNames)) {
                    $table->index('cui');
                }
                if (Schema::hasColumn('tenants', 'country') && !in_array('tenants_country_index', $indexNames)) {
                    $table->index('country');
                }
                if (Schema::hasColumn('tenants', 'billing_starts_at') && !in_array('tenants_billing_starts_at_index', $indexNames)) {
                    $table->index('billing_starts_at');
                }
            });
        } catch (\Exception $e) {
            // Indexes might already exist, that's fine
        }

        // Set billing_starts_at to created_at for existing tenants
        DB::statement('UPDATE tenants SET billing_starts_at = created_at WHERE billing_starts_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Drop indexes if they exist
            try {
                $table->dropIndex(['cui']);
            } catch (\Exception $e) {}
            try {
                $table->dropIndex(['country']);
            } catch (\Exception $e) {}
            try {
                $table->dropIndex(['billing_starts_at']);
            } catch (\Exception $e) {}

            // Drop columns that exist
            $columnsToDrop = [];
            foreach ([
                'company_name',
                'cui',
                'reg_com',
                'bank_account',
                'bank_name',
                'address',
                'city',
                'state',
                'country',
                'billing_starts_at',
                'billing_cycle_days',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
