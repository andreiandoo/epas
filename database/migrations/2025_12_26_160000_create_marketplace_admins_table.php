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
        // Marketplace Admins - Admin users for each marketplace client
        Schema::create('marketplace_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();

            // Account details
            $table->string('email');
            $table->string('password');
            $table->string('name');
            $table->string('phone')->nullable();

            // Role within the marketplace
            $table->string('role')->default('admin'); // super_admin, admin, moderator
            $table->json('permissions')->nullable(); // Granular permissions

            // Status
            $table->string('status')->default('active'); // active, suspended
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            // Settings
            $table->json('settings')->nullable();
            $table->string('locale')->default('ro');
            $table->string('timezone')->default('Europe/Bucharest');

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Email is unique per marketplace client
            $table->unique(['marketplace_client_id', 'email']);
            $table->index(['marketplace_client_id', 'status']);
        });

        // Add password reset support
        Schema::table('marketplace_password_resets', function (Blueprint $table) {
            $table->string('user_type')->default('customer')->after('email');
            // user_type: customer, organizer, admin
        });

        // Marketplace Carts - Session-based carts for customers
        Schema::create('marketplace_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->foreignId('marketplace_customer_id')->nullable()->constrained()->nullOnDelete();

            // Cart contents
            $table->json('items')->nullable();
            $table->json('promo_code')->nullable();

            // Totals (cached for performance)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency')->default('RON');

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_carts');

        Schema::table('marketplace_password_resets', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });

        Schema::dropIfExists('marketplace_admins');
    }
};
