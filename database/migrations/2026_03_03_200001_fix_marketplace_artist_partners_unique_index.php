<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add unique index if it doesn't exist (on fresh installs, migration 200000 already created it)
        try {
            Schema::table('marketplace_artist_partners', function (Blueprint $table) {
                $table->unique(['marketplace_client_id', 'artist_id'], 'mp_artist_partners_client_artist_unique');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Index already exists — skip
        }

        // Migrate existing data if not already done
        if (DB::table('marketplace_artist_partners')->count() === 0) {
            DB::table('artists')
                ->whereNotNull('marketplace_client_id')
                ->select('id', 'marketplace_client_id', 'is_partner', 'partner_notes')
                ->chunkById(200, function ($artists) {
                    foreach ($artists as $artist) {
                        DB::table('marketplace_artist_partners')->insertOrIgnore([
                            'marketplace_client_id' => $artist->marketplace_client_id,
                            'artist_id'             => $artist->id,
                            'is_partner'            => $artist->is_partner ?? true,
                            'partner_notes'         => $artist->partner_notes ?? null,
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        try {
            Schema::table('marketplace_artist_partners', function (Blueprint $table) {
                $table->dropUnique('mp_artist_partners_client_artist_unique');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Index doesn't exist — skip
        }
    }
};
