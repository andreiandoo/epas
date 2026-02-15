<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_event_sources')) {
            return;
        }

        Schema::create('affiliate_event_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_event_sources');
    }
};
