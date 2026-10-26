<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contul de cumpărător Tixello și registrul acordurilor. Aici Tixello e operator
 * separat (vezi /politica-confidentialitate, secțiunea 4.2): un cont apare doar
 * când cumpărătorul bifează la plată sau își face cont la /contul-meu.
 * Tabele noi; nimic existent nu se schimbă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tixello_buyer_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            // Contul cu bilete (bifa „păstrează biletele în contul meu Tixello”)
            $table->boolean('account_enabled')->default(false);
            $table->timestamp('account_enabled_at')->nullable();
            // Informări și recomandări de la Tixello
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();
            $table->timestamp('marketing_opt_out_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Dovada fiecărui acord dat sau retras (art. 7 alin. 1 GDPR): textul exact, versiunea, sursa
        Schema::create('tixello_buyer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('tixello_buyer_accounts')->nullOnDelete();
            $table->string('email', 190)->index();
            $table->string('type', 20); // account | marketing
            $table->string('action', 20); // granted | withdrawn
            $table->string('text_version', 32)->nullable();
            $table->text('text')->nullable();
            $table->string('source', 30); // checkout | portal | unsubscribe | deletion
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tixello_buyer_consents');
        Schema::dropIfExists('tixello_buyer_accounts');
    }
};
