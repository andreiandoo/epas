<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media-partners microservice (slug: media-partners) — external partners
 * (e.g. the Maximum Rock magazine) that call /api/partner/v1 with their own key.
 *
 * The key is stored only as a SHA-256 hash and carries explicit scopes, unlike
 * the marketplace client key, which also reaches orders and stats.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->string('name', 191);
            // Used as utm_source and as the article source name.
            $table->string('slug', 64);
            // active | inactive
            $table->string('status', 16)->default('active');

            // First characters of the plain key, so admins can tell keys apart.
            $table->string('api_key_prefix', 16)->nullable();
            $table->string('api_key_hash', 64)->nullable()->unique();
            // e.g. ["events:read", "artists:read"]
            $table->json('scopes')->nullable();
            // Exact IPs or CIDR ranges; empty = any IP.
            $table->json('allowed_ips')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(120);

            // utm.source / utm.medium / utm.campaign
            $table->json('settings')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_partners');
    }
};
