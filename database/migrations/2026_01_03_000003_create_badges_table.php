<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();

            // Basic info
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->string('slug');
            $table->string('icon_url')->nullable();
            $table->string('color', 7)->default('#6366F1'); // Hex color

            // Category
            $table->enum('category', [
                'milestone',   // e.g., First purchase, 10th event
                'activity',    // e.g., Check-in master, Review champion
                'special',     // e.g., Early bird, VIP
                'event',       // e.g., Attended specific event type
                'loyalty',     // e.g., 1 year member, Tier upgrades
                'social',      // e.g., Referral champion, Social sharer
            ])->default('milestone');

            // Rewards for earning badge
            $table->integer('xp_reward')->default(0); // XP awarded when badge earned
            $table->integer('bonus_points')->default(0); // Bonus points awarded

            // Conditions (flexible JSON rule engine)
            // Example: {"type": "compound", "operator": "AND", "rules": [
            //   {"metric": "events_attended", "operator": ">=", "value": 10},
            //   {"metric": "genre_attendance", "operator": ">=", "value": 5, "params": {"genre_id": 3}}
            // ]}
            $table->json('conditions');

            // Display settings
            $table->boolean('is_secret')->default(false); // Hidden until earned
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->tinyInteger('rarity_level')->default(1); // 1-5 (common to legendary)
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'category']);
            $table->index(['marketplace_client_id', 'is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
