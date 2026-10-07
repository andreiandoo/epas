<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The checkout funnel gained two events (ViewCart, AddPaymentInfo). Connections keep
 * an explicit list of allowed events, so existing ones that already send
 * InitiateCheckout get the two new steps as well; others are left untouched.
 */
return new class extends Migration
{
    private const NEW_EVENTS = ['ViewCart', 'AddPaymentInfo'];

    public function up(): void
    {
        if (!Schema::hasTable('facebook_capi_connections')) {
            return;
        }

        foreach (DB::table('facebook_capi_connections')->get(['id', 'enabled_events']) as $row) {
            $events = json_decode((string) $row->enabled_events, true);
            if (!is_array($events) || !in_array('InitiateCheckout', $events, true)) {
                continue;
            }

            $merged = array_values(array_unique(array_merge($events, self::NEW_EVENTS)));
            if ($merged !== $events) {
                DB::table('facebook_capi_connections')
                    ->where('id', $row->id)
                    ->update(['enabled_events' => json_encode($merged)]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('facebook_capi_connections')) {
            return;
        }

        foreach (DB::table('facebook_capi_connections')->get(['id', 'enabled_events']) as $row) {
            $events = json_decode((string) $row->enabled_events, true);
            if (!is_array($events)) {
                continue;
            }

            $kept = array_values(array_diff($events, self::NEW_EVENTS));
            if ($kept !== $events) {
                DB::table('facebook_capi_connections')
                    ->where('id', $row->id)
                    ->update(['enabled_events' => json_encode($kept)]);
            }
        }
    }
};
