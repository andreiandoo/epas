<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra pentru contul de client tenant: beneficiari (cont de familie),
 * metode de plată salvate (tokenizate) și carduri cadou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_customer_beneficiaries')) {
            Schema::create('tenant_customer_beneficiaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('name');
                $table->string('relation')->default('adult'); // adult | child
                $table->string('email')->nullable();
                $table->date('birthdate')->nullable();
                $table->string('status')->default('active');   // active | invited
                $table->jsonb('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_customer_payment_methods')) {
            Schema::create('tenant_customer_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('brand')->default('card');      // visa | mastercard | card
                $table->string('last4', 4);
                $table->unsignedTinyInteger('exp_month')->nullable();
                $table->unsignedSmallInteger('exp_year')->nullable();
                $table->string('holder')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('token')->nullable();           // placeholder tokenizare (nu stocăm PAN)
                $table->jsonb('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_gift_cards')) {
            Schema::create('tenant_gift_cards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index(); // proprietar (după activare)
                $table->string('code')->unique();
                $table->unsignedInteger('initial_cents')->default(0);
                $table->unsignedInteger('balance_cents')->default(0);
                $table->string('status')->default('active');   // active | partial | used
                $table->string('recipient_name')->nullable();
                $table->text('message')->nullable();
                $table->jsonb('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_gift_cards');
        Schema::dropIfExists('tenant_customer_payment_methods');
        Schema::dropIfExists('tenant_customer_beneficiaries');
    }
};
