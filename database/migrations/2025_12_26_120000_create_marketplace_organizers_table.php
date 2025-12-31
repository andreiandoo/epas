<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Marketplace Organizers - Event creators on a marketplace
        Schema::create('marketplace_organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();

            // Account details
            $table->string('email')->unique();
            $table->string('password');
            $table->string('name'); // Organization/Company name
            $table->string('slug')->unique();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();

            // Company details
            $table->string('company_name')->nullable();
            $table->string('company_tax_id')->nullable(); // CUI/VAT
            $table->string('company_registration')->nullable(); // J number
            $table->text('company_address')->nullable();

            // Profile
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();

            // Status
            $table->string('status')->default('pending'); // pending, active, suspended
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // Commission (can override marketplace default)
            $table->decimal('commission_rate', 5, 2)->nullable(); // null = use marketplace default

            // Settings
            $table->json('settings')->nullable();
            $table->json('payout_details')->nullable(); // Bank account, etc.

            // Stats
            $table->unsignedBigInteger('total_events')->default(0);
            $table->unsignedBigInteger('total_tickets_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'status']);
            $table->index('email');
        });

        // Marketplace Customers - Ticket buyers on a marketplace
        Schema::create('marketplace_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();

            // Account details
            $table->string('email');
            $table->string('password')->nullable(); // Nullable for guest checkout
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();

            // Profile
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('locale')->default('ro');

            // Address
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('RO');

            // Status
            $table->string('status')->default('active'); // active, suspended
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Marketing
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();

            // Stats
            $table->unsignedBigInteger('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Email is unique per marketplace client
            $table->unique(['marketplace_client_id', 'email']);
            $table->index(['marketplace_client_id', 'status']);
        });

        // Marketplace Events - Events created by marketplace organizers
        // These are separate from tenant events but use similar structure
        Schema::create('marketplace_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained()->cascadeOnDelete();

            // Event details
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            // Dates
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('doors_open_at')->nullable();

            // Venue (reference to core venues, but organizer can't own)
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('venue_name')->nullable(); // For display if venue not in system
            $table->text('venue_address')->nullable();
            $table->string('venue_city')->nullable();

            // Category & Classification
            $table->string('category')->nullable();
            $table->json('tags')->nullable();

            // Media
            $table->string('image')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();

            // Settings
            $table->string('status')->default('draft'); // draft, pending_review, published, cancelled
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('capacity')->nullable();

            // Sales settings
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->integer('max_tickets_per_order')->default(10);

            // Approval
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();

            // Stats (cached)
            $table->unsignedBigInteger('tickets_sold')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedBigInteger('views')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_client_id', 'slug']);
            $table->index(['marketplace_client_id', 'status', 'starts_at']);
            $table->index(['marketplace_organizer_id', 'status']);
        });

        // Marketplace Ticket Types
        Schema::create('marketplace_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_event_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('RON');

            // Inventory
            $table->integer('quantity')->nullable(); // null = unlimited
            $table->integer('quantity_sold')->default(0);
            $table->integer('quantity_reserved')->default(0);

            // Limits
            $table->integer('min_per_order')->default(1);
            $table->integer('max_per_order')->default(10);

            // Sales window
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();

            // Status
            $table->string('status')->default('on_sale'); // on_sale, paused, sold_out, hidden
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_event_id', 'status']);
        });

        // Add marketplace fields to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('marketplace_client_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('marketplace_organizer_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('marketplace_customer_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('marketplace_event_id')->nullable()
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropForeign(['marketplace_customer_id']);
            $table->dropForeign(['marketplace_event_id']);
            $table->dropColumn(['marketplace_client_id', 'marketplace_organizer_id', 'marketplace_customer_id', 'marketplace_event_id']);
        });

        Schema::dropIfExists('marketplace_ticket_types');
        Schema::dropIfExists('marketplace_events');
        Schema::dropIfExists('marketplace_customers');
        Schema::dropIfExists('marketplace_organizers');
    }
};
