<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->longText('html_content')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_templates', function (Blueprint $table) {
            $table->longText('html_content')->nullable(false)->change();
        });
    }
};
