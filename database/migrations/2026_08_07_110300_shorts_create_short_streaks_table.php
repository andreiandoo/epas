<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watch streaks + a daily points ledger for shorts (D11).
 *
 * The existing Gamification tables hang off `Customer`, while the mobile app's
 * buyer is a `MarketplaceCustomer`; the bridge between the two is still open
 * (docs/plans/friends-social.md §0). This table keeps the marketplace-side state
 * so the loop works today, and ShortGamificationService forwards to the real
 * points ledger once the bridge exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_streaks')) {
            return;
        }

        Schema::create('short_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_watch_date')->nullable();
            $table->unsignedBigInteger('total_points')->default(0);
            // Daily cap enforcement (anti-abuse, D11): points already granted today.
            $table->unsignedInteger('points_today')->default(0);
            $table->date('points_today_date')->nullable();
            $table->timestamps();

            $table->unique('marketplace_customer_id', 'short_streaks_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_streaks');
    }
};
