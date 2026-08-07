<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal stand-ins for the upstream tables the Shorts feature points at.
 *
 * The full 747-migration history cannot replay on SQLite (a pre-existing
 * migration drops an indexed column, which SQLite refuses) so the shorts test
 * suite builds a scoped schema instead: these stubs, then every shorts
 * migration. Only the columns Shorts actually reads are modelled here.
 *
 * Never registered in database/migrations — this path is loaded exclusively by
 * Tests\Shorts\ShortsTestCase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketplace_clients')) {
            Schema::create('marketplace_clients', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketplace_customers')) {
            Schema::create('marketplace_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('locale')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // MarketplaceCustomerObserver syncs new customers into dynamic contact
        // lists on create — the stub keeps that lookup from exploding.
        if (! Schema::hasTable('marketplace_contact_lists')) {
            Schema::create('marketplace_contact_lists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->nullable();
                $table->string('name')->nullable();
                $table->string('list_type')->default('manual');
                $table->json('rules')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('marketplace_contact_list_subscribers')) {
            Schema::create('marketplace_contact_list_subscribers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_contact_list_id')->nullable();
                $table->foreignId('marketplace_customer_id')->nullable();
                $table->string('status')->default('subscribed');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable();
                $table->string('slug')->nullable();
                $table->string('title')->nullable();
                $table->date('event_date')->nullable();
                $table->string('city')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('artists')) {
            Schema::create('artists', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('youtube_id')->nullable();
                $table->string('youtube_url')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ticket_types')) {
            Schema::create('ticket_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->nullable();
                $table->string('name')->nullable();
                $table->integer('price')->default(0);
                $table->string('currency', 3)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable();
                $table->foreignId('event_id')->nullable();
                $table->foreignId('marketplace_customer_id')->nullable();
                $table->string('status')->nullable();
                $table->string('payment_status')->nullable();
                $table->decimal('total', 12, 2)->default(0);
                $table->string('currency', 3)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Scoped test schema — torn down with the in-memory database.
    }
};
