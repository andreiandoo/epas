<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-bounce tracking for marketplace customers.
 *
 * `email_suppressed` already covers HARD problems (hard bounce, complaint,
 * spam-trap, block, unsubscribe) — those are excluded from sends. Soft bounces
 * (temporary: mailbox full, greylisting, server unavailable) are NOT
 * suppressions, so they lived nowhere. These columns let us count + exclude
 * them from newsletter audiences, and self-heal them when a later send is
 * delivered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_customers', 'email_soft_bounced_at')) {
                $table->timestamp('email_soft_bounced_at')->nullable()->after('email_suppressed_at');
            }
            if (!Schema::hasColumn('marketplace_customers', 'email_soft_bounce_count')) {
                $table->unsignedInteger('email_soft_bounce_count')->default(0)->after('email_soft_bounced_at');
            }
            $table->index('email_soft_bounced_at', 'idx_mp_customers_email_soft_bounced');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->dropIndex('idx_mp_customers_email_soft_bounced');
            $table->dropColumn(['email_soft_bounced_at', 'email_soft_bounce_count']);
        });
    }
};
