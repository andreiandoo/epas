<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schemă redusă pentru suita widget-ului de Android.
 *
 * Istoricul complet de migrații nu poate fi rulat pe SQLite (vezi DECISIONS.md
 * D-002), deci suita își construiește doar tabelele pe care le citește
 * `TixelloWidgetStatsService`, plus cele fără de care modelele nu pot fi
 * salvate (activity log, listele de contacte atinse de observer).
 *
 * Nu e înregistrată în `database/migrations` — o încarcă exclusiv
 * Tests\Feature\TixelloWidget\TixelloWidgetTestCase.
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
                $table->string('status')->default('active');
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
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable();
                $table->foreignId('marketplace_client_id')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->timestamps();
                $table->softDeletes();
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
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        /* MarketplaceCustomerObserver scrie aici la fiecare client nou. */
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
                $table->foreignId('marketplace_client_id')->nullable();
                $table->string('slug')->nullable();
                $table->string('title')->nullable();
                $table->string('event_series')->nullable();
                $table->date('event_date')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketplace_events')) {
            Schema::create('marketplace_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->nullable();
                $table->foreignId('marketplace_organizer_id')->nullable();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamps();
                /* Modelul real e soft-deleting; fără coloană, orice eager load
                   pe relaţia `marketplaceEvent` crapă cu „no such column". */
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable();
                $table->foreignId('event_id')->nullable();
                $table->foreignId('marketplace_client_id')->nullable();
                $table->foreignId('marketplace_event_id')->nullable();
                $table->foreignId('customer_id')->nullable();
                $table->foreignId('marketplace_customer_id')->nullable();
                $table->string('order_number')->nullable();
                $table->string('status')->nullable();
                $table->string('payment_status')->nullable();
                /* Ambele forme de sumă coexistă în producție: comenzile vechi
                   au doar `total_cents`, cele noi `total`. Serviciul cade de pe
                   una pe alta, deci suita are nevoie de amândouă. */
                $table->decimal('total', 12, 2)->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('commission_rate', 8, 4)->nullable();
                $table->string('currency', 3)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable();
                $table->foreignId('ticket_type_id')->nullable();
                $table->string('name')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable();
                $table->foreignId('event_id')->nullable();
                $table->string('code')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('base_currency', 3);
                $table->string('target_currency', 3);
                $table->decimal('rate', 18, 6);
                $table->string('source')->nullable();
                $table->timestamps();
            });
        }

        /* spatie/laravel-activitylog: Order folosește LogsActivity, deci orice
           salvare scrie aici. */
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->id();
                $table->string('log_name')->nullable();
                $table->text('description')->nullable();
                $table->nullableMorphs('subject', 'subject');
                $table->nullableMorphs('causer', 'causer');
                $table->json('properties')->nullable();
                $table->string('event')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'activity_log', 'exchange_rates', 'tickets', 'order_items', 'orders',
            'marketplace_events', 'events', 'marketplace_contact_list_subscribers',
            'marketplace_contact_lists', 'marketplace_customers', 'customers',
            'marketplace_clients', 'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
