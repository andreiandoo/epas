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
        if (Schema::hasTable('marketplace_client_microservices')) {
            return;
        }

        Schema::create('marketplace_client_microservices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('microservice_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'microservice_id'], 'mpc_microservice_unique');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_client_microservices');
    }
};
