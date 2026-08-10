<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 3 — money and compliance.
 *
 *  D3  short_promotions      : paid boosts, with pacing and frequency capping
 *  D7  rights columns        : ownership, licence window, territory, age rating
 *  B9  UGC columns           : attendee-submitted shorts, gated on a scanned ticket
 *  B10 short_poster_variants : cover A/B testing
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_promotions')) {
            Schema::create('short_promotions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->index();
                $table->string('model', 8)->default('cpm');          // cpm|cpc
                $table->unsignedBigInteger('bid_cents');
                $table->unsignedBigInteger('budget_cents');
                $table->unsignedBigInteger('spent_cents')->default(0);
                $table->json('targeting')->nullable();               // {geo, genres, age}
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->string('status', 16)->default('pending');    // pending|active|paused|ended|rejected
                $table->timestamps();

                // The eligibility query scans exactly this.
                $table->index(['status', 'start_at', 'end_at']);
            });
        }

        if (! Schema::hasTable('short_promotion_events')) {
            Schema::create('short_promotion_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_promotion_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_customer_id')->nullable()->index();
                $table->string('type', 16);                          // impression|click
                $table->unsignedBigInteger('charged_cents')->default(0);
                $table->timestamp('created_at')->useCurrent();

                // Billing is reconstructed from these rows, so they are kept
                // separate from short_events, which gets pruned.
                $table->index(['short_promotion_id', 'type', 'created_at']);
            });
        }

        if (! Schema::hasTable('short_poster_variants')) {
            Schema::create('short_poster_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->string('poster_path');
                $table->string('label')->nullable();
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->boolean('is_winner')->default(false);
                $table->timestamps();

                $table->index(['short_id', 'is_winner']);
            });
        }

        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                // D7 — rights and licensing.
                if (! Schema::hasColumn('shorts', 'rights_holder')) {
                    $table->string('rights_holder')->nullable();
                }
                if (! Schema::hasColumn('shorts', 'license_type')) {
                    $table->string('license_type', 16)->default('owned'); // owned|licensed|ugc|artist|partner
                }
                if (! Schema::hasColumn('shorts', 'usage_expires_at')) {
                    $table->timestamp('usage_expires_at')->nullable();
                }
                if (! Schema::hasColumn('shorts', 'territories')) {
                    $table->json('territories')->nullable();           // {mode:'allow'|'deny', codes:[...]}
                }
                if (! Schema::hasColumn('shorts', 'age_rating')) {
                    $table->unsignedTinyInteger('age_rating')->default(0); // 0|16|18
                }

                // B9 — UGC.
                if (! Schema::hasColumn('shorts', 'is_ugc')) {
                    $table->boolean('is_ugc')->default(false);
                }
                if (! Schema::hasColumn('shorts', 'author_marketplace_customer_id')) {
                    $table->foreignId('author_marketplace_customer_id')->nullable()->index();
                }
                if (! Schema::hasColumn('shorts', 'reports_count')) {
                    $table->unsignedInteger('reports_count')->default(0);
                }
            });
        }

        if (! Schema::hasTable('short_reports')) {
            Schema::create('short_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_customer_id')->nullable()->index();
                $table->string('reason', 64);
                $table->text('detail')->nullable();
                $table->string('status', 16)->default('open');        // open|dismissed|actioned
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('short_promotion_events');
        Schema::dropIfExists('short_promotions');
        Schema::dropIfExists('short_poster_variants');
        Schema::dropIfExists('short_reports');

        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->dropColumn([
                    'rights_holder', 'license_type', 'usage_expires_at', 'territories',
                    'age_rating', 'is_ugc', 'author_marketplace_customer_id', 'reports_count',
                ]);
            });
        }
    }
};
