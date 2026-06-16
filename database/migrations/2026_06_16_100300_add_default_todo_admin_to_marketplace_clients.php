<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            // Marketplace admin who receives every newly created TODO by
            // default. For Ambilet this is marketplace_admin #5.
            $table->foreignId('default_todo_admin_id')
                ->nullable()
                ->after('settings')
                ->constrained('marketplace_admins')
                ->nullOnDelete();
        });

        // Seed the Ambilet default to admin #5 (only if both rows exist).
        $ambilet = DB::table('marketplace_clients')->where('slug', 'ambilet')->first();
        $admin5 = DB::table('marketplace_admins')->where('id', 5)->first();
        if ($ambilet && $admin5 && (int) $admin5->marketplace_client_id === (int) $ambilet->id) {
            DB::table('marketplace_clients')
                ->where('id', $ambilet->id)
                ->update(['default_todo_admin_id' => 5]);
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_todo_admin_id');
        });
    }
};
