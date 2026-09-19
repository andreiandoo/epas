<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Seif" (Settings → Seif in the marketplace panel): credentials that only
 * the super admins picked on each entry can see.
 *
 * url, username, password, phone and email are stored encrypted with APP_KEY
 * (the model uses the `encrypted` cast), so they are TEXT columns. Only the
 * name stays readable, for search and sorting.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_vault_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->string('name', 191);
            $table->text('url')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('requires_2fa')->default(false);
            $table->text('phone')->nullable();
            $table->text('email')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('marketplace_admins')
                ->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('marketplace_admins')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'name']);
        });

        // Which super admins may open an entry. No row = no access, even for
        // a super admin.
        Schema::create('marketplace_vault_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_entry_id')
                ->constrained('marketplace_vault_entries')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_admin_id')
                ->constrained('marketplace_admins')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vault_entry_id', 'marketplace_admin_id'], 'mva_entry_admin_unique');
            $table->index('marketplace_admin_id', 'mva_admin_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_vault_access');
        Schema::dropIfExists('marketplace_vault_entries');
    }
};
