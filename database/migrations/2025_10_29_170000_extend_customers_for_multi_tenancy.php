<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable()->after('tenant_id');
            $table->string('last_name', 120)->nullable()->after('first_name');
            // entry-point / primary owner, poate fi diferit de apartenențele din pivot
            $table->foreignId('primary_tenant_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('tenants')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // pivot many-to-many Customer <-> Tenant
        Schema::create('customer_tenant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'tenant_id']);
            $table->index(['tenant_id', 'customer_id']);
        });

        // backfill: atașează toți clienții la tenantul din orders unde au cumpărat
        DB::transaction(function () {
            $pairs = DB::table('orders')
                ->select('customer_id', 'tenant_id')
                ->whereNotNull('customer_id')
                ->groupBy('customer_id', 'tenant_id')
                ->get();

            foreach ($pairs as $p) {
                // inserează dacă nu există
                $exists = DB::table('customer_tenant')
                    ->where('customer_id', $p->customer_id)
                    ->where('tenant_id', $p->tenant_id)
                    ->exists();

                if (!$exists) {
                    DB::table('customer_tenant')->insert([
                        'customer_id' => $p->customer_id,
                        'tenant_id'   => $p->tenant_id,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                // setează primary_tenant_id dacă e null
                $hasPrimary = DB::table('customers')
                    ->where('id', $p->customer_id)
                    ->value('primary_tenant_id');

                if (!$hasPrimary) {
                    DB::table('customers')
                        ->where('id', $p->customer_id)
                        ->update(['primary_tenant_id' => $p->tenant_id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tenant');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_tenant_id');
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
