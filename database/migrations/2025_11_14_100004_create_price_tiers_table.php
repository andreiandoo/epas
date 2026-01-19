<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_tiers')) {
            return;
        }

        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('currency', 3)->default('USD');
            $table->integer('price_cents');
            $table->string('color_hex', 7)->default('#10B981');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
