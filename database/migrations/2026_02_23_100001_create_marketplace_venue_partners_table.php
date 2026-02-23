<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for many-to-many marketplace ↔ venue relationship
        Schema::create('marketplace_venue_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();
            $table->boolean('is_partner')->default(true);
            $table->text('partner_notes')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'venue_id']);
        });

        // Migrate existing data from venues.marketplace_client_id to the pivot table
        DB::table('venues')
            ->whereNotNull('marketplace_client_id')
            ->select('id', 'marketplace_client_id', 'is_partner', 'partner_notes')
            ->chunkById(200, function ($venues) {
                foreach ($venues as $venue) {
                    DB::table('marketplace_venue_partners')->insertOrIgnore([
                        'marketplace_client_id' => $venue->marketplace_client_id,
                        'venue_id'              => $venue->id,
                        'is_partner'            => $venue->is_partner ?? true,
                        'partner_notes'         => $venue->partner_notes ?? null,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Restore data back to venues table before dropping pivot
        $rows = DB::table('marketplace_venue_partners')->get();
        foreach ($rows as $row) {
            DB::table('venues')
                ->where('id', $row->venue_id)
                ->update([
                    'marketplace_client_id' => $row->marketplace_client_id,
                    'is_partner'            => $row->is_partner,
                    'partner_notes'         => $row->partner_notes,
                ]);
        }

        Schema::dropIfExists('marketplace_venue_partners');
    }
};
