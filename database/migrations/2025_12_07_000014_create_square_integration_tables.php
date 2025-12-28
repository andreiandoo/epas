<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Square OAuth connections
        Schema::create('square_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('merchant_id');
            $table->string('business_name')->nullable();
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('environment')->default('production'); // sandbox, production
            $table->json('location_ids')->nullable(); // Linked Square locations
            $table->string('status')->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'merchant_id']);
        });

        // Square locations
        Schema::create('square_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('location_id');
            $table->string('name');
            $table->string('status')->nullable(); // ACTIVE, INACTIVE
            $table->string('type')->nullable(); // PHYSICAL, MOBILE
            $table->json('address')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'location_id']);
        });

        // Square catalog items synced
        Schema::create('square_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('catalog_object_id');
            $table->string('type'); // ITEM, ITEM_VARIATION, CATEGORY, DISCOUNT, TAX
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price_cents')->nullable();
            $table->string('currency')->nullable();
            $table->string('sku')->nullable();
            $table->string('category_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->string('local_type')->nullable(); // tickets, products
            $table->unsignedBigInteger('local_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('cascade');
            $table->index(['catalog_object_id']);
            $table->index(['local_type', 'local_id']);
        });

        // Square orders/transactions
        Schema::create('square_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('order_id'); // Square order ID
            $table->string('location_id');
            $table->string('state'); // OPEN, COMPLETED, CANCELED
            $table->integer('total_money_cents');
            $table->string('currency');
            $table->json('line_items')->nullable();
            $table->json('fulfillments')->nullable();
            $table->string('source')->nullable(); // SQUARE_POS, ONLINE
            $table->string('local_type')->nullable(); // orders
            $table->unsignedBigInteger('local_id')->nullable();
            $table->timestamp('created_at_square')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('cascade');
            $table->index(['order_id']);
            $table->index(['local_type', 'local_id']);
        });

        // Square payments
        Schema::create('square_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('square_order_id')->nullable();
            $table->string('payment_id');
            $table->string('order_id')->nullable();
            $table->string('location_id');
            $table->integer('amount_cents');
            $table->string('currency');
            $table->string('status'); // APPROVED, COMPLETED, CANCELED, FAILED
            $table->string('source_type')->nullable(); // CARD, CASH, WALLET
            $table->json('card_details')->nullable();
            $table->string('receipt_url')->nullable();
            $table->timestamp('created_at_square')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('cascade');
            $table->foreign('square_order_id')->references('id')->on('square_orders')->onDelete('set null');
            $table->index(['payment_id']);
        });

        // Square webhooks
        Schema::create('square_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('subscription_id')->nullable();
            $table->string('signature_key'); // For verifying webhooks
            $table->json('event_types'); // payment.completed, order.created, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('cascade');
        });

        // Square webhook events log
        Schema::create('square_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->string('event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('square_connections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('square_webhook_events');
        Schema::dropIfExists('square_webhooks');
        Schema::dropIfExists('square_payments');
        Schema::dropIfExists('square_orders');
        Schema::dropIfExists('square_catalog_items');
        Schema::dropIfExists('square_locations');
        Schema::dropIfExists('square_connections');
    }
};
