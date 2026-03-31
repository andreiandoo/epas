<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->boolean('by_proxy')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->dropColumn('by_proxy');
        });
    }
};
