<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email-suppression flag for marketplace customers — added 2026-06-18
 * after Brevo blocked the sending account citing 79 spam-trap hits and
 * 74 invalid MX domains. Two new columns let us mark known-bad
 * addresses and explain why; the rest of the platform reads
 * `email_suppressed=true` and skips that customer from any send
 * (newsletter, transactional, etc.).
 *
 * Reasons in use:
 *  - invalid_mx       → domain has no DNS MX record (customers:audit-email-mx)
 *  - brevo_hard_bounce → permanent bounce reported by Brevo
 *  - brevo_spam_trap   → spam trap hit reported by Brevo
 *  - brevo_complaint   → spam complaint (user clicked "this is spam")
 *  - brevo_unsubscribed → unsubscribed via Brevo (separate from our
 *    accepts_marketing flag so we don't conflate consent with reputation)
 *  - manual            → flagged by an admin
 *
 * Non-breaking: every existing customer rows in at default
 * (email_suppressed=false, no reason, no timestamp), so the field is
 * additive — code that doesn't yet check it keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->boolean('email_suppressed')->default(false)->after('accepts_marketing');
            $table->string('email_suppression_reason', 50)->nullable()->after('email_suppressed');
            $table->timestamp('email_suppressed_at')->nullable()->after('email_suppression_reason');
            $table->index('email_suppressed', 'idx_mp_customers_email_suppressed');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->dropIndex('idx_mp_customers_email_suppressed');
            $table->dropColumn(['email_suppressed', 'email_suppression_reason', 'email_suppressed_at']);
        });
    }
};
