<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deactivate badges whose conditions cannot be determined from marketplace data.
     *
     * Undeterminable badges:
     * - rock-veteran: compound condition requiring genre_attendance(rock) — no genre ID mapping
     * - comeback-kid: requires tracking inactivity gaps
     * - reviewer: reviews not implemented for marketplace
     * - photographer: no photo upload feature
     * - beta-tester: manual award only, cannot auto-determine
     * - new-years-eve: event_tag_attendance — no tag tracking
     * - front-row: front_row_purchases — seat row data unavailable in marketplace orders
     * - metalhead: genre_attendance(metal) — no genre ID mapping
     */
    public function up(): void
    {
        $slugsToDeactivate = [
            'rock-veteran',
            'comeback-kid',
            'reviewer',
            'photographer',
            'beta-tester',
            'new-years-eve',
            'front-row',
            'metalhead',
        ];

        DB::table('badges')
            ->whereIn('slug', $slugsToDeactivate)
            ->update(['is_active' => false]);
    }

    /**
     * Re-activate the badges.
     */
    public function down(): void
    {
        $slugsToReactivate = [
            'rock-veteran',
            'comeback-kid',
            'reviewer',
            'photographer',
            'beta-tester',
            'new-years-eve',
            'front-row',
            'metalhead',
        ];

        DB::table('badges')
            ->whereIn('slug', $slugsToReactivate)
            ->update(['is_active' => true]);
    }
};
