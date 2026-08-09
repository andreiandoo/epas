<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advertisers — who is paying for a promoted short (D3, extended).
 *
 * The original short_promotions table assumed the payer is always a tenant, so
 * the only thing that could be advertised was an organiser's own event. That
 * covers "boost my event" but not the two other things the feed needs to sell:
 *
 *   - house ads    : our own cross-promotion, no money involved;
 *   - brand ads    : a third party with no tenant account at all.
 *
 * So the payer becomes its own row. `tenant_id` stays (it is what scopes the
 * organiser panel) but is no longer required, and every promotion points at an
 * advertiser that carries the prepaid balance.
 *
 * Money is prepaid credit rather than post-paid invoicing: serving an ad we
 * cannot bill for is the one failure mode with no recovery path, and a balance
 * check is cheap enough to run on every impression.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_advertisers')) {
            Schema::create('short_advertisers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                // tenant   : an organiser boosting their own catalogue
                // house    : our own inventory — never billed, fills empty slots
                // external : a brand with no tenant account
                $table->string('type', 16)->default('tenant');
                $table->foreignId('tenant_id')->nullable()->index();
                $table->string('contact_email')->nullable();
                $table->string('website')->nullable();
                // Prepaid balance. Charges debit it; it may not go negative, and
                // an advertiser at zero stops being served on the next request.
                $table->unsignedBigInteger('credit_cents')->default(0);
                $table->string('status', 16)->default('active');   // active|blocked
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'type']);
            });
        }

        if (! Schema::hasTable('short_advertiser_transactions')) {
            Schema::create('short_advertiser_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_advertiser_id')->constrained()->cascadeOnDelete();
                $table->foreignId('short_promotion_id')->nullable()->index();
                $table->string('type', 16);                          // topup|charge|refund
                // Signed: a charge is negative, a top-up positive, so the ledger
                // sums to the balance and a mismatch is detectable.
                $table->bigInteger('amount_cents');
                $table->unsignedBigInteger('balance_after_cents')->default(0);
                $table->string('reference')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['short_advertiser_id', 'created_at']);
            });
        }

        if (Schema::hasTable('short_promotions')) {
            Schema::table('short_promotions', function (Blueprint $table) {
                if (! Schema::hasColumn('short_promotions', 'short_advertiser_id')) {
                    $table->foreignId('short_advertiser_id')->nullable()->index();
                }

                // What is being sold. The machinery is identical; the objective
                // drives the disclosure wording and the reporting split, because
                // "boosted event" and "brand ad" are the same slot to us and very
                // different things to a viewer and to a regulator.
                if (! Schema::hasColumn('short_promotions', 'objective')) {
                    $table->string('objective', 16)->default('event'); // event|artist|brand|house
                }

                // Overrides the default "Sponsorizat". A brand ad must be able to
                // say "Reclamă"; conflating the two is the kind of thing consumer
                // protection authorities fine you for.
                if (! Schema::hasColumn('short_promotions', 'disclosure_label')) {
                    $table->string('disclosure_label', 64)->nullable();
                }

                // Per-campaign override of the global daily frequency cap.
                if (! Schema::hasColumn('short_promotions', 'frequency_cap')) {
                    $table->unsignedTinyInteger('frequency_cap')->nullable();
                }

                // House ads lose every auction on price (they bid nothing), so
                // they need a separate lane: they only fill slots no paid ad
                // wanted. Higher priority wins within the same lane.
                if (! Schema::hasColumn('short_promotions', 'priority')) {
                    $table->unsignedSmallInteger('priority')->default(0);
                }

                if (! Schema::hasColumn('short_promotions', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }

                if (! Schema::hasColumn('short_promotions', 'rejection_reason')) {
                    $table->string('rejection_reason')->nullable();
                }
            });

            $this->relaxTenantId();
        }
    }

    /**
     * tenant_id was NOT NULL because a promotion could only ever belong to an
     * organiser. A brand advertiser has no tenant, so the column has to accept
     * null. It is a plain unsignedBigInteger with an index — no foreign key —
     * so dropping the NOT NULL is the whole change on Postgres and MySQL.
     *
     * SQLite is left alone deliberately: it cannot ALTER a column without
     * rebuilding the table, and the rebuild would drop the foreign key on
     * short_id. Since SQLite is only ever the local/test engine here, writing a
     * tenant_id of 0 for non-tenant advertisers there is the cheaper trade —
     * see ShortPromotion::booted().
     */
    private function relaxTenantId(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE short_promotions ALTER COLUMN tenant_id DROP NOT NULL');

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE short_promotions MODIFY tenant_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('short_advertiser_transactions');
        Schema::dropIfExists('short_advertisers');

        if (Schema::hasTable('short_promotions')) {
            Schema::table('short_promotions', function (Blueprint $table) {
                $table->dropColumn([
                    'short_advertiser_id', 'objective', 'disclosure_label',
                    'frequency_cap', 'priority', 'approved_at', 'rejection_reason',
                ]);
            });
        }
    }
};
