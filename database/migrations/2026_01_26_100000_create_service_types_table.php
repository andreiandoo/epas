<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('code', 50); // featuring, email, tracking, campaign
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('pricing'); // Pricing configuration JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'code']);
            $table->index(['marketplace_client_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
