<?php

namespace App\Http\Controllers\Api\Partner;

use App\Models\PartnerAd;
use App\Models\PartnerAdStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Daily impressions and clicks a partner reports for the ads it shows.
 */
class AdStatsController extends PartnerController
{
    private const MAX_ITEMS = 1000;

    /**
     * POST /ads/stats  { "date": "2026-09-20", "items": [{ "ambilet_ad_id": 17, "impressions": 12840, "clicks": 96 }] }
     *
     * Idempotent per date and ad: sending a day again replaces its numbers.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS],
            'items.*.ambilet_ad_id' => ['required', 'integer'],
            'items.*.impressions' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'items.*.clicks' => ['required', 'integer', 'min:0', 'max:2000000000'],
        ]);

        if ($validator->fails()) {
            return $this->fail('invalid_parameter', $validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $partner = $this->partner($request);
        $items = collect($request->input('items'))->keyBy(fn ($item) => (int) $item['ambilet_ad_id']);

        $ownAdIds = PartnerAd::where('marketplace_partner_id', $partner->id)
            ->whereIn('id', $items->keys())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $now = now();
        $rows = $ownAdIds->map(fn (int $adId) => [
            'partner_ad_id' => $adId,
            'date' => $request->input('date'),
            'impressions' => (int) $items[$adId]['impressions'],
            'clicks' => (int) $items[$adId]['clicks'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        if ($rows) {
            PartnerAdStat::upsert($rows, ['partner_ad_id', 'date'], ['impressions', 'clicks', 'updated_at']);
        }

        return response()->json([
            'data' => [
                'date' => $request->input('date'),
                'stored' => count($rows),
                'unknown_ad_ids' => $items->keys()->diff($ownAdIds)->values()->all(),
            ],
        ]);
    }
}
