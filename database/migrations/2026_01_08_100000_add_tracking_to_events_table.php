<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0)->after('is_cancelled');
            $table->unsignedBigInteger('interested_count')->default(0)->after('views_count');
        });

        // Create table to track which customers are interested in which events
        Schema::create('event_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->nullable()->constrained('marketplace_customers')->onDelete('cascade');
            $table->string('session_id')->nullable(); // For anonymous users
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate interests
            $table->unique(['event_id', 'marketplace_customer_id'], 'event_customer_interest_unique');
            $table->unique(['event_id', 'session_id'], 'event_session_interest_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_interests');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'interested_count']);
        });
    }
};
