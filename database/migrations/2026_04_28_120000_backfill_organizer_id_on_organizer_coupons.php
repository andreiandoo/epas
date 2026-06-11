<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Coupons mirrored from /organizator/promo before the marketplace_organizer_id
        // backfill was added to the controller. Resolve organizer from the matching
        // MarketplaceOrganizerPromoCode (same code + same marketplace_client_id).
        $rows = DB::table('coupon_codes')
            ->where('source', 'organizer')
            ->whereNull('marketplace_organizer_id')
            ->whereNull('deleted_at')
            ->get(['id', 'marketplace_client_id', 'code']);

        foreach ($rows as $row) {
            $organizerId = DB::table('mkt_promo_codes')
                ->where('marketplace_client_id', $row->marketplace_client_id)
                ->where('code', $row->code)
                ->value('marketplace_organizer_id');

            if ($organizerId) {
                DB::table('coupon_codes')
                    ->where('id', $row->id)
                    ->update(['marketplace_organizer_id' => $organizerId]);
            }
        }
    }

    public function down(): void
    {
        // No-op — we don't undo the scope tightening.
    }
};
