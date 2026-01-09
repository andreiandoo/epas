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
        if (Schema::hasTable('promo_code_templates')) {
            return;
        }

        Schema::create('promo_code_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['fixed', 'percentage']);
            $table->decimal('value', 10, 2);
            $table->enum('applies_to', ['cart', 'event', 'ticket_type'])->default('cart');
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('min_tickets')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_customer')->nullable();
            $table->integer('validity_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_templates');
    }
};
