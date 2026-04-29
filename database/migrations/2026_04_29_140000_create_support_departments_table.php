<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->string('slug', 64);
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('notify_emails')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['marketplace_client_id', 'is_active', 'sort_order'], 'support_dept_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_departments');
    }
};
