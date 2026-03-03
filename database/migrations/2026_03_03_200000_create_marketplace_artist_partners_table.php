<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for many-to-many marketplace ↔ artist relationship
        Schema::create('marketplace_artist_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('artist_id')
                ->constrained('artists')
                ->cascadeOnDelete();
            $table->boolean('is_partner')->default(true);
            $table->text('partner_notes')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'artist_id']);
        });

        // Migrate existing data from artists.marketplace_client_id to the pivot table
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

    public function down(): void
    {
        // Restore data back to artists table before dropping pivot
        $rows = DB::table('marketplace_artist_partners')->get();
        foreach ($rows as $row) {
            DB::table('artists')
                ->where('id', $row->artist_id)
                ->update([
                    'marketplace_client_id' => $row->marketplace_client_id,
                    'is_partner'            => $row->is_partner,
                    'partner_notes'         => $row->partner_notes,
                ]);
        }

        Schema::dropIfExists('marketplace_artist_partners');
    }
};
