<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_customer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_event_id')->constrained()->onDelete('cascade');

            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->text('text');
            $table->json('detailed_ratings')->nullable(); // {show: 4, venue: 5, organization: 4, value: 3}
            $table->json('photos')->nullable(); // Array of photo URLs

            $table->boolean('recommend')->default(true);
            $table->boolean('is_anonymous')->default(false);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);

            $table->timestamps();

            // Each customer can only review an event once
            $table->unique(['marketplace_customer_id', 'marketplace_event_id'], 'mcr_customer_event_unique');

            // Indexes for filtering
            $table->index(['marketplace_client_id', 'status'], 'mcr_client_status_idx');
            $table->index(['marketplace_event_id', 'status'], 'mcr_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_reviews');
    }
};
