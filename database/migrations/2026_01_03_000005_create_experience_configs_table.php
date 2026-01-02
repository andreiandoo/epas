<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->unique()->constrained()->cascadeOnDelete();

            // Naming (translatable)
            $table->json('xp_name')->nullable(); // e.g., {"en": "Experience", "ro": "Experiență"}
            $table->json('level_name')->nullable(); // e.g., {"en": "Level", "ro": "Nivel"}
            $table->string('icon')->default('star');

            // Level progression formula
            $table->enum('level_formula', ['linear', 'exponential', 'custom'])->default('exponential');
            $table->integer('base_xp_per_level')->default(100); // XP needed for level 1->2
            $table->decimal('level_multiplier', 5, 2)->default(1.5); // For exponential: each level needs base * multiplier^(level-1)
            $table->json('custom_levels')->nullable(); // For custom: [{"level": 1, "xp_required": 100}, {"level": 2, "xp_required": 250}, ...]

            // Level groups (e.g., Bronze levels 1-5, Silver levels 6-10)
            // [{"name": "Bronze", "min_level": 1, "max_level": 5, "color": "#CD7F32", "icon": "bronze-badge"},
            //  {"name": "Silver", "min_level": 6, "max_level": 10, "color": "#C0C0C0"},
            //  {"name": "Gold", "min_level": 11, "max_level": 15, "color": "#FFD700"},
            //  {"name": "RockStar", "min_level": 16, "max_level": 99, "color": "#9333EA"}]
            $table->json('level_groups')->nullable();

            // Level rewards (bonuses at specific levels)
            // [{"level": 5, "bonus_points": 100, "badge_id": 1},
            //  {"level": 10, "bonus_points": 250, "reward_id": 2}]
            $table->json('level_rewards')->nullable();

            // Limits
            $table->integer('max_level')->default(100);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_configs');
    }
};
