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
        if (Schema::hasTable('microservices')) {
            return;
        }

        Schema::create('microservices', function (Blueprint $table) {
            $table->id();

            // Unique identifier slug
            $table->string('slug')->unique()->index();

            // Display name
            $table->string('name');

            // Description
            $table->text('description');

            // Pricing
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time'])->default('monthly');
            $table->enum('pricing_model', ['recurring', 'one_time', 'usage'])->default('recurring');

            // Features list
            $table->json('features')->nullable();

            // Category/tags
            $table->string('category')->nullable()->index();

            // Status
            $table->enum('status', ['active', 'beta', 'deprecated', 'disabled'])->default('active')->index();

            // Dependencies (other microservices required)
            $table->json('dependencies')->nullable()->comment('Array of microservice slugs required');

            // Metadata (endpoints, database tables, etc.)
            $table->json('metadata')->nullable();

            // Icon/image
            $table->string('icon')->nullable();

            // Documentation URL
            $table->string('documentation_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'category'], 'idx_status_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('microservices');
    }
};
