<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facebook_ads_accounts')) {
            Schema::create('facebook_ads_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable();
                $table->unsignedBigInteger('marketplace_client_id')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('connection_id')->nullable();
                $table->string('fb_account_id', 50);
                $table->string('account_name')->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('account_status', 50)->nullable();
                $table->string('timezone_name', 50)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_status', 20)->nullable();
                $table->text('last_sync_error')->nullable();
                $table->timestamps();

                $table->foreign('marketplace_organizer_id')
                    ->references('id')->on('marketplace_organizers')
                    ->nullOnDelete();
                $table->foreign('connection_id')
                    ->references('id')->on('facebook_capi_connections')
                    ->nullOnDelete();

                $table->index('fb_account_id', 'fb_ads_acc_id_idx');
                $table->index('marketplace_organizer_id', 'fb_ads_acc_org_idx');
            });
        }

        if (!Schema::hasTable('facebook_ads_campaigns')) {
            Schema::create('facebook_ads_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ads_account_id')
                    ->constrained('facebook_ads_accounts')
                    ->cascadeOnDelete();
                $table->string('fb_campaign_id', 50);
                $table->string('name')->nullable();
                $table->string('objective', 100)->nullable();
                $table->string('status', 50)->nullable();
                $table->string('effective_status', 50)->nullable();
                $table->decimal('daily_budget', 12, 2)->nullable();
                $table->decimal('lifetime_budget', 12, 2)->nullable();
                $table->string('budget_currency', 10)->nullable();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('stop_time')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['ads_account_id', 'fb_campaign_id'], 'fb_ads_camp_acc_camp_uq');
                $table->index('fb_campaign_id', 'fb_ads_camp_id_idx');
            });
        }

        if (!Schema::hasTable('facebook_ads_insights')) {
            Schema::create('facebook_ads_insights', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ads_account_id')
                    ->constrained('facebook_ads_accounts')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->string('fb_campaign_id', 50)->nullable();
                $table->date('date');
                $table->bigInteger('impressions')->default(0);
                $table->bigInteger('reach')->default(0);
                $table->bigInteger('clicks')->default(0);
                $table->decimal('spend', 12, 2)->default(0);
                $table->decimal('ctr', 8, 4)->default(0); // %
                $table->decimal('cpc', 12, 4)->default(0);
                $table->decimal('cpm', 12, 4)->default(0);
                $table->bigInteger('conversions')->default(0); // from Meta-side attribution
                $table->decimal('conversion_value', 14, 2)->default(0); // Meta-side
                $table->json('actions')->nullable(); // raw breakdown
                $table->json('action_values')->nullable();
                $table->string('currency', 10)->nullable();
                $table->timestamps();

                $table->foreign('campaign_id')
                    ->references('id')->on('facebook_ads_campaigns')
                    ->nullOnDelete();

                $table->unique(
                    ['ads_account_id', 'fb_campaign_id', 'date'],
                    'fb_ads_insights_acc_camp_date_uq'
                );
                $table->index('date', 'fb_ads_insights_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ads_insights');
        Schema::dropIfExists('facebook_ads_campaigns');
        Schema::dropIfExists('facebook_ads_accounts');
    }
};
