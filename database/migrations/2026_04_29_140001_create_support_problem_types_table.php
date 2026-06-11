<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_problem_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('support_department_id')
                ->constrained('support_departments')
                ->cascadeOnDelete();
            $table->string('slug', 64);
            $table->json('name');
            $table->json('description')->nullable();
            // Which extra fields the form should require for this problem type.
            // Values from a fixed vocabulary: url, invoice_series, invoice_number,
            // event_id, module_name. Drives conditional inputs on the organizer form
            // and validation in the API controller.
            $table->json('required_fields')->nullable();
            // Restrict to specific opener types: ['organizer'], ['customer'], or both.
            // Lets us hide "settlement issues" from customers when they get tickets later.
            $table->json('allowed_opener_types')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['support_department_id', 'slug']);
            $table->index(['marketplace_client_id', 'is_active', 'sort_order'], 'support_pt_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_problem_types');
    }
};
