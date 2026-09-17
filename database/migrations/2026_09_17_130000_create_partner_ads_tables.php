<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media-partners microservice — ads created in the marketplace admin and
 * published on a partner site (e.g. Maximum Rock ad zones).
 *
 * partner_ad_formats caches the partner's GET /ads/slots answer; partner_ads
 * are pushed with signed PUT/DELETE requests; partner_ad_stats holds the daily
 * impressions and clicks the partner reports back.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_partners', function (Blueprint $table) {
            // Base URL of the partner's ads API, e.g. https://www.maximumrock.ro/wp-json/maximumrock/v1
            $table->string('ads_api_url', 2048)->nullable()->after('outbound_secret');
        });

        Schema::create('partner_ad_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_partner_id')
                ->constrained('marketplace_partners')
                ->cascadeOnDelete();

            $table->string('slot', 64);
            $table->string('slot_label', 191)->nullable();
            $table->string('format', 64);
            $table->string('label', 191)->nullable();
            // The partner's format object: image / image_mobile sizes, fields, max_length.
            $table->json('spec')->nullable();
            // false once the partner stops offering the format.
            $table->boolean('is_available')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Formats are defined per zone; two zones may reuse a code like "300x250".
            $table->unique(['marketplace_partner_id', 'slot', 'format']);
        });

        Schema::create('partner_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_partner_id')
                ->constrained('marketplace_partners')
                ->cascadeOnDelete();

            $table->string('name', 191);
            $table->string('slot', 64);
            $table->string('format', 64);

            $table->string('title', 191)->nullable();
            $table->string('text', 500)->nullable();
            $table->string('cta', 64)->nullable();
            $table->string('image_path', 1024)->nullable();
            $table->string('image_mobile_path', 1024)->nullable();

            // event | url
            $table->string('link_type', 16)->default('event');
            // Not FK-constrained: deleting an event withdraws the ad instead of blocking.
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('custom_url', 2048)->nullable();
            $table->string('utm_campaign', 100)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(1);

            // What the admin wants: draft | active | paused
            $table->string('status', 16)->default('draft');
            // Why the ad was paused automatically (event cancelled, deleted...).
            $table->string('paused_reason', 191)->nullable();

            // Where it stands on the partner site: pending | published | withdrawn | failed
            $table->string('sync_status', 16)->nullable();
            $table->string('remote_id', 64)->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->unsignedBigInteger('created_by_marketplace_admin_id')->nullable();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'status']);
            $table->index('event_id');
        });

        Schema::create('partner_ad_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_ad_id')
                ->constrained('partner_ads')
                ->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['partner_ad_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_ad_stats');
        Schema::dropIfExists('partner_ads');
        Schema::dropIfExists('partner_ad_formats');

        Schema::table('marketplace_partners', function (Blueprint $table) {
            $table->dropColumn('ads_api_url');
        });
    }
};
