<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('marketplace_client_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('artist_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();

            $table->string('slug')->nullable()->after('name');
            $table->string('status', 32)->default('planning')->after('type')
                ->comment('planning|announced|on_sale|in_progress|completed|cancelled');

            $table->date('start_date')->nullable()->after('status');
            $table->date('end_date')->nullable()->after('start_date');

            $table->integer('budget_cents')->nullable()->after('end_date');
            $table->string('currency', 3)->default('EUR')->after('budget_cents');

            $table->string('poster_url')->nullable()->after('currency');
            $table->jsonb('description')->nullable()->after('poster_url')
                ->comment('Translatable');

            $table->string('routing_notes')->nullable()->after('description')
                ->comment('Internal notes about tour routing/logistics');

            $table->json('meta')->nullable()->after('routing_notes');

            $table->index(['tenant_id']);
            $table->index(['artist_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['artist_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['artist_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'tenant_id', 'artist_id', 'slug', 'status',
                'start_date', 'end_date', 'budget_cents', 'currency',
                'poster_url', 'description', 'routing_notes', 'meta',
            ]);
        });
    }
};
