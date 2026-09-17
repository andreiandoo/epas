<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media-partners microservice — requests we send to a partner (event webhooks
 * now, ad publishing later), signed with a shared secret, with a delivery log.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_partners', function (Blueprint $table) {
            // Where event.created / updated / cancelled / deleted notifications go.
            $table->string('webhook_url', 2048)->nullable()->after('settings');
            // Shared HMAC-SHA256 secret for requests we send; encrypted at rest.
            $table->text('outbound_secret')->nullable()->after('webhook_url');
        });

        Schema::create('marketplace_partner_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_partner_id')
                ->constrained('marketplace_partners')
                ->cascadeOnDelete();

            // ping | event.created | event.updated | event.cancelled | event.deleted
            $table->string('type', 32);
            // The event id (or, later, the ad id) the request is about.
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('method', 8)->default('POST');
            $table->string('url', 2048);
            $table->json('body')->nullable();

            // pending | sent | failed
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_partner_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_partner_deliveries');

        Schema::table('marketplace_partners', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'outbound_secret']);
        });
    }
};
