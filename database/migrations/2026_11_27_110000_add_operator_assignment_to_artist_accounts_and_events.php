<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // „Preiau” pe conturi artist și evenimente de revizuit (bara de atenție a operatorilor).
    // Coloane nullable, fără FK, ca să nu blocăm tabelul events la adăugare.
    public function up(): void
    {
        foreach (['marketplace_artist_accounts', 'events'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'assigned_to_marketplace_admin_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unsignedBigInteger('assigned_to_marketplace_admin_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->index('assigned_to_marketplace_admin_id', $tableName . '_assigned_admin_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['marketplace_artist_accounts', 'events'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'assigned_to_marketplace_admin_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName . '_assigned_admin_idx');
                $table->dropColumn(['assigned_to_marketplace_admin_id', 'assigned_at']);
            });
        }
    }
};
