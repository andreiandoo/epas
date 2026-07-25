<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_artists', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_artists', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_artists', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_artists', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
