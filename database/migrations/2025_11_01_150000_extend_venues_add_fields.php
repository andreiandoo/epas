<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ---- Postgres-safe: facem tenant_id nullable și FK cu ON DELETE SET NULL
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // 1) scoatem vechiul FK (dacă există)
            DB::statement('ALTER TABLE venues DROP CONSTRAINT IF EXISTS venues_tenant_id_foreign');
            // 2) facem coloana nullable
            DB::statement('ALTER TABLE venues ALTER COLUMN tenant_id DROP NOT NULL');
            // 3) adăugăm FK cu ON DELETE SET NULL
            DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL');
        } else {
            // MySQL fallback (dacă vei rula pe alt mediu)
            Schema::table('venues', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
            Schema::table('venues', function (Blueprint $table) {
                // Pentru change() pe MySQL e nevoie de doctrine/dbal; dacă nu e instalat, folosește SQL brut similar.
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
            Schema::table('venues', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        // ---- Câmpurile noi (doar dacă nu există)
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'state')) {
                $table->string('state', 120)->nullable(); // județ
            }
            if (!Schema::hasColumn('venues', 'capacity_total')) {
                $table->integer('capacity_total')->nullable();
            }
            if (!Schema::hasColumn('venues', 'capacity_standing')) {
                $table->integer('capacity_standing')->nullable();
            }
            if (!Schema::hasColumn('venues', 'capacity_seated')) {
                $table->integer('capacity_seated')->nullable();
            }
            if (!Schema::hasColumn('venues', 'image_url')) {
                $table->string('image_url', 255)->nullable();
            }
            if (!Schema::hasColumn('venues', 'phone')) {
                $table->string('phone', 64)->nullable();
            }
            if (!Schema::hasColumn('venues', 'email')) {
                $table->string('email', 190)->nullable();
            }
            if (!Schema::hasColumn('venues', 'facebook_url')) {
                $table->string('facebook_url', 255)->nullable();
            }
            if (!Schema::hasColumn('venues', 'instagram_url')) {
                $table->string('instagram_url', 255)->nullable();
            }
            if (!Schema::hasColumn('venues', 'tiktok_url')) {
                $table->string('tiktok_url', 255)->nullable();
            }
            if (!Schema::hasColumn('venues', 'established_at')) {
                $table->date('established_at')->nullable();
            }
            if (!Schema::hasColumn('venues', 'description')) {
                $table->longText('description')->nullable();
            }
        });

        // Populate rapid capacity_total din capacity, dacă există date
        try {
            DB::statement("UPDATE venues SET capacity_total = capacity WHERE capacity_total IS NULL AND capacity IS NOT NULL");
        } catch (\Throwable $e) {
            // ignorăm dacă nu există coloana legacy 'capacity' în unele instanțe
        }
    }

    public function down(): void
    {
        // Revert: tenant_id din nou NOT NULL + ON DELETE CASCADE (cum era uzual)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE venues DROP CONSTRAINT IF EXISTS venues_tenant_id_foreign');
            DB::statement('ALTER TABLE venues ALTER COLUMN tenant_id SET NOT NULL');
            DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE');
        } else {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
            Schema::table('venues', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
            Schema::table('venues', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'state','capacity_total','capacity_standing','capacity_seated',
                'image_url','phone','email','facebook_url','instagram_url','tiktok_url',
                'established_at','description',
            ]);
        });
    }
};
