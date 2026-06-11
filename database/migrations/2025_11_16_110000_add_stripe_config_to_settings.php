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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('stripe_mode')->default('test')->after('invoice_footer'); // test or live
            $table->text('stripe_test_public_key')->nullable()->after('stripe_mode');
            $table->text('stripe_test_secret_key')->nullable()->after('stripe_test_public_key');
            $table->text('stripe_live_public_key')->nullable()->after('stripe_test_secret_key');
            $table->text('stripe_live_secret_key')->nullable()->after('stripe_live_public_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_live_secret_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_mode',
                'stripe_test_public_key',
                'stripe_test_secret_key',
                'stripe_live_public_key',
                'stripe_live_secret_key',
                'stripe_webhook_secret',
            ]);
        });
    }
};
