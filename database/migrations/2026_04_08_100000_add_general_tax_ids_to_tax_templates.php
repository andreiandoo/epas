<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->jsonb('general_tax_ids')->nullable()->after('by_proxy');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->dropColumn('general_tax_ids');
        });
    }
};
