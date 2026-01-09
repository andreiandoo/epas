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
        if (Schema::hasTable('platform_costs')) {
            return;
        }

        Schema::create('platform_costs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable()->index(); // server, domain, cdn, service, other
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time'])->default('monthly');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_costs');
    }
};
