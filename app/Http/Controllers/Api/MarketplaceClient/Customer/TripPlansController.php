<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saved itineraries from the bilete.online trip planner (/plan).
 *
 * The planner is a browser application: it builds, edits and shares a plan without a server, and
 * this controller only exists for travellers who want one kept against their account. The payload
 * is the planner's own state, stored as it comes and handed back unchanged — the catalogue stays
 * the source of truth for names, photos and coordinates, so a saved plan cannot go stale.
 *
 *   GET    /customer/trip-plans            list
 *   GET    /customer/trip-plans/{token}    one
 *   POST   /customer/trip-plans            create, or update when a token is given
 *   DELETE /customer/trip-plans/{token}    remove
 */
class TripPlansController extends BaseController
{
    private const MAX_PLANS = 40;
    private const MAX_PAYLOAD = 64000;   // bytes; a 7-day plan is a couple of KB

    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $rows = DB::table('marketplace_trip_plans')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PLANS)
            ->get(['token', 'title', 'place', 'days', 'stops', 'starts_on', 'updated_at']);

        return $this->success([
            'plans' => $rows->map(fn ($r) => [
                'token'      => $r->token,
                'title'      => $r->title,
                'place'      => $r->place,
                'days'       => (int) $r->days,
                'stops'      => (int) $r->stops,
                'starts_on'  => $r->starts_on,
                'updated_at' => $r->updated_at,
            ])->values()->all(),
        ]);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $row = DB::table('marketplace_trip_plans')
            ->where('token', $token)
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }

        return $this->success(['plan' => $this->payload($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $data = $request->validate([
            'token'     => 'nullable|string|max:32',
            'title'     => 'required|string|max:160',
            'place'     => 'nullable|string|max:120',
            'days'      => 'required|integer|min:1|max:31',
            'stops'     => 'nullable|integer|min:0|max:500',
            'starts_on' => 'nullable|date',
            'payload'   => 'required|array',
        ]);

        $encoded = json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD) {
            return response()->json(['success' => false, 'message' => 'Plan payload too large'], 422);
        }

        $now = now();
        $fields = [
            'title'      => $data['title'],
            'place'      => $data['place'] ?? null,
            'days'       => (int) $data['days'],
            'stops'      => (int) ($data['stops'] ?? 0),
            'starts_on'  => $data['starts_on'] ?? null,
            'payload'    => $encoded,
            'updated_at' => $now,
        ];

        // A token that belongs to this customer means "save over that one".
        $existing = ! empty($data['token'])
            ? DB::table('marketplace_trip_plans')
                ->where('token', $data['token'])
                ->where('marketplace_customer_id', $customer->id)
                ->where('marketplace_client_id', $client->id)
                ->first()
            : null;

        if ($existing) {
            DB::table('marketplace_trip_plans')->where('id', $existing->id)->update($fields);
            $token = $existing->token;
        } else {
            $count = DB::table('marketplace_trip_plans')
                ->where('marketplace_customer_id', $customer->id)
                ->where('marketplace_client_id', $client->id)
                ->count();
            if ($count >= self::MAX_PLANS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ai atins limita de ' . self::MAX_PLANS . ' planuri salvate. Șterge unul înainte să salvezi altul.',
                ], 422);
            }

            $token = Str::lower(Str::random(24));
            DB::table('marketplace_trip_plans')->insert($fields + [
                'marketplace_client_id'   => $client->id,
                'marketplace_customer_id' => $customer->id,
                'token'                   => $token,
                'created_at'              => $now,
            ]);
        }

        $row = DB::table('marketplace_trip_plans')->where('token', $token)->first();

        return $this->success(['plan' => $this->payload($row)]);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $deleted = DB::table('marketplace_trip_plans')
            ->where('token', $token)
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }

        return $this->success(['deleted' => true]);
    }

    private function payload(object $row): array
    {
        return [
            'token'      => $row->token,
            'title'      => $row->title,
            'place'      => $row->place,
            'days'       => (int) $row->days,
            'stops'      => (int) $row->stops,
            'starts_on'  => $row->starts_on,
            'payload'    => json_decode((string) $row->payload, true) ?: [],
            'updated_at' => $row->updated_at,
        ];
    }
}
