<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // General Taxes
        Schema::create('general_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained('event_types')->cascadeOnUpdate()->nullOnDelete();
            $table->string('name', 190);
            $table->decimal('value', 10, 4)->default(0);
            $table->enum('value_type', ['percent', 'fixed'])->default('percent');
            $table->string('currency', 3)->nullable(); // For fixed value taxes (ISO 4217)
            $table->text('explanation')->nullable();
            $table->integer('priority')->default(0); // Higher priority = applied first
            $table->date('valid_from')->nullable(); // Tax validity start date
            $table->date('valid_until')->nullable(); // Tax validity end date
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index('event_type_id');
            $table->index(['valid_from', 'valid_until']);
            $table->index('priority');
            // Unique constraint: same tenant + event_type + name combination
            $table->unique(['tenant_id', 'event_type_id', 'name'], 'general_taxes_unique');
        });

        // Local Taxes
        Schema::create('local_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('country', 100);
            $table->string('county', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('value', 10, 4)->default(0); // Always percent for local taxes
            $table->text('explanation')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->integer('priority')->default(0); // Higher priority = applied first
            $table->date('valid_from')->nullable(); // Tax validity start date
            $table->date('valid_until')->nullable(); // Tax validity end date
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['country', 'county', 'city']);
            $table->index(['valid_from', 'valid_until']);
            $table->index('priority');
        });

        // Pivot table for local taxes and event types (many-to-many)
        Schema::create('local_tax_event_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_tax_id')->constrained('local_taxes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['local_tax_id', 'event_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_tax_event_type');
        Schema::dropIfExists('local_taxes');
        Schema::dropIfExists('general_taxes');
    }
};
