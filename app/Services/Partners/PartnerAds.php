<?php

namespace App\Services\Partners;

use App\Jobs\DeliverPartnerRequestJob;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplacePartner;
use App\Models\MarketplacePartnerDelivery;
use App\Models\PartnerAd;
use App\Models\PartnerAdFormat;
use App\Models\PartnerEventFeedItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ads published on a media partner's site: its formats (GET /ads/slots), and
 * each ad pushed with a signed PUT while it should run or DELETE otherwise.
 */
class PartnerAds
{
    public const TYPE_UPSERT = 'ad.upsert';
    public const TYPE_DELETE = 'ad.delete';

    /**
     * Fetch and store the partner's ad formats.
     *
     * @return int formats the partner offers
     *
     * @throws \RuntimeException with a message for the admin
     */
    public function syncFormats(MarketplacePartner $partner): int
    {
        $base = rtrim((string) $partner->ads_api_url, '/');
        $secret = (string) $partner->outbound_secret;

        if ($base === '' || $secret === '') {
            throw new \RuntimeException('Setează la partener adresa API pentru reclame și secretul de semnare.');
        }

        $url = $base . '/ads/slots';
        PartnerRequestSender::assertPublicHttpsUrl($url);

        $timestamp = time();
        $prefix = PartnerRequestSender::headerPrefixFor($partner, 'ad.slots');

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Tixello-Partner-API/1.0',
                "{$prefix}-Timestamp" => (string) $timestamp,
                "{$prefix}-Signature" => PartnerRequestSender::signature($secret, $timestamp, ''),
            ])->withoutRedirecting()->connectTimeout(5)->timeout(15)->get($url);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Partenerul nu răspunde: ' . Str::limit($e->getMessage(), 200));
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Partenerul a răspuns HTTP ' . $response->status() . '.');
        }

        $slots = $response->json('slots');
        if (!is_array($slots)) {
            throw new \RuntimeException('Răspunsul partenerului nu conține lista „slots”.');
        }

        $text = fn ($value, int $length) => is_scalar($value) && (string) $value !== ''
            ? mb_substr((string) $value, 0, $length)
            : null;

        $seenIds = [];
        foreach ($slots as $slot) {
            $slotCode = is_array($slot) ? $text($slot['slot'] ?? null, 64) : null;
            if ($slotCode === null) {
                continue;
            }

            // A zone without a "formats" list counts as a single format named after the zone.
            $formats = is_array($slot['formats'] ?? null) && $slot['formats'] !== []
                ? $slot['formats']
                : [array_merge($slot, ['format' => $slotCode])];

            foreach ($formats as $format) {
                $code = is_array($format) ? $text($format['format'] ?? null, 64) : null;
                if ($code === null || str_contains($code, '|') || str_contains($slotCode, '|')) {
                    continue;
                }

                $row = PartnerAdFormat::updateOrCreate(
                    ['marketplace_partner_id' => $partner->id, 'slot' => $slotCode, 'format' => $code],
                    [
                        'slot_label' => $text($slot['label'] ?? null, 191),
                        'label' => $text($format['label'] ?? null, 191),
                        'spec' => Arr::only($format, ['image', 'image_mobile', 'fields', 'max_length']),
                        'is_available' => true,
                        'synced_at' => now(),
                    ]
                );
                $seenIds[] = $row->id;
            }
        }

        PartnerAdFormat::where('marketplace_partner_id', $partner->id)
            ->when($seenIds, fn ($q) => $q->whereNotIn('id', $seenIds))
            ->update(['is_available' => false]);

        return count($seenIds);
    }

    /**
     * Bring the partner site in line with the ad: PUT while it should run,
     * DELETE when it should not and was sent before.
     */
    public function sync(PartnerAd $ad): ?MarketplacePartnerDelivery
    {
        // Loaded fresh: table queries may have loaded only a few partner columns.
        $partner = MarketplacePartner::find($ad->marketplace_partner_id);
        $ad->setRelation('partner', $partner);
        $base = rtrim((string) $partner?->ads_api_url, '/');

        if (!$partner || $base === '') {
            $ad->forceFill([
                'sync_status' => PartnerAd::SYNC_FAILED,
                'sync_error' => 'Partenerul nu are adresa API pentru reclame.',
            ])->save();

            return null;
        }

        $shouldRun = $ad->status === PartnerAd::STATUS_ACTIVE && (!$ad->ends_at || $ad->ends_at->isFuture());

        if (!$shouldRun && !$this->wasSent($ad)) {
            return null;
        }

        return $this->queue($ad, $partner, $shouldRun ? self::TYPE_UPSERT : self::TYPE_DELETE);
    }

    /**
     * Before an ad is deleted here: take it off the partner site too.
     */
    public function withdrawBeforeDelete(PartnerAd $ad): void
    {
        $partner = MarketplacePartner::find($ad->marketplace_partner_id);

        if ($partner && $partner->ads_api_url && $this->wasSent($ad)) {
            $ad->setRelation('partner', $partner);
            $this->queue($ad, $partner, self::TYPE_DELETE);
        }
    }

    /**
     * Pause active ads whose event can no longer be sold: cancelled, deleted,
     * unpublished or over. Paused ads are withdrawn from the partner site.
     *
     * @return int ads paused
     */
    public function pauseForUnavailableEvents(MarketplaceClient $client): int
    {
        $ads = PartnerAd::where('marketplace_client_id', $client->id)
            ->where('status', PartnerAd::STATUS_ACTIVE)
            ->where('link_type', 'event')
            ->whereNotNull('event_id')
            ->get();

        if ($ads->isEmpty()) {
            return 0;
        }

        $rows = PartnerEventFeedItem::where('marketplace_client_id', $client->id)
            ->whereIn('event_id', $ads->pluck('event_id')->unique())
            ->get()
            ->keyBy('event_id');

        $paused = 0;
        foreach ($ads as $ad) {
            $row = $rows->get($ad->event_id);

            $reason = match (true) {
                $row === null => Event::whereKey($ad->event_id)->exists() ? null : 'Evenimentul a fost șters.',
                $row->removed_at !== null => 'Evenimentul a fost șters sau depublicat.',
                $row->status === 'cancelled' => 'Evenimentul a fost anulat.',
                $row->listed_until !== null && $row->listed_until->isPast() => 'Evenimentul s-a încheiat.',
                default => null,
            };

            if ($reason === null) {
                continue;
            }

            $ad->forceFill(['status' => PartnerAd::STATUS_PAUSED, 'paused_reason' => $reason])->save();
            $this->sync($ad);
            $paused++;
        }

        return $paused;
    }

    /**
     * Record on the ad how its latest request ended. Called once per delivery,
     * when it is sent or finally fails.
     */
    public function deliveryFinished(MarketplacePartnerDelivery $delivery): void
    {
        $ad = PartnerAd::find($delivery->subject_id);

        $superseded = MarketplacePartnerDelivery::where('marketplace_partner_id', $delivery->marketplace_partner_id)
            ->whereIn('type', [self::TYPE_UPSERT, self::TYPE_DELETE])
            ->where('subject_id', $delivery->subject_id)
            ->where('id', '>', $delivery->id)
            ->exists();

        if (!$ad || $superseded) {
            return;
        }

        $sent = $delivery->status === MarketplacePartnerDelivery::STATUS_SENT;

        if ($delivery->type === self::TYPE_DELETE) {
            // Already gone on the partner side counts as withdrawn.
            $gone = $sent || in_array($delivery->http_status, [404, 410], true);

            $ad->forceFill($gone
                ? ['sync_status' => PartnerAd::SYNC_WITHDRAWN, 'sync_error' => null, 'synced_at' => now(), 'remote_id' => null]
                : ['sync_status' => PartnerAd::SYNC_FAILED, 'sync_error' => $this->errorMessage($delivery)]
            )->save();

            return;
        }

        if ($sent) {
            $body = json_decode((string) $delivery->response_excerpt, true);
            $ad->forceFill([
                'sync_status' => PartnerAd::SYNC_PUBLISHED,
                'sync_error' => null,
                'synced_at' => now(),
                'remote_id' => is_array($body) && isset($body['id']) ? mb_substr((string) $body['id'], 0, 64) : $ad->remote_id,
            ])->save();

            return;
        }

        $ad->forceFill([
            'sync_status' => PartnerAd::SYNC_FAILED,
            'sync_error' => $this->errorMessage($delivery),
        ])->save();
    }

    /**
     * The body sent with PUT /ads/{id}.
     */
    public function payload(PartnerAd $ad): array
    {
        $timezone = PartnerEventPresenter::timezoneFor($ad->partner->marketplaceClient);

        return [
            'slot' => $ad->slot,
            'format' => $ad->format,
            'title' => $ad->title,
            'text' => $ad->text,
            'cta' => $ad->cta,
            'url' => $this->targetUrl($ad),
            'image' => $ad->image_path ? Storage::disk('public')->url($ad->image_path) : null,
            'image_mobile' => $ad->image_mobile_path ? Storage::disk('public')->url($ad->image_mobile_path) : null,
            'background' => null,
            'html' => null,
            'event_id' => $ad->link_type === 'event' ? $ad->event_id : null,
            'starts_at' => $ad->starts_at?->copy()->setTimezone($timezone)->toIso8601String(),
            'ends_at' => $ad->ends_at?->copy()->setTimezone($timezone)->toIso8601String(),
            'status' => 'active',
            'priority' => $ad->priority,
        ];
    }

    /**
     * Where a click goes: the event page or the custom link, with display UTM parameters.
     */
    public function targetUrl(PartnerAd $ad): ?string
    {
        $client = $ad->partner->marketplaceClient;

        if ($ad->link_type === 'event') {
            $row = PartnerEventFeedItem::where('marketplace_client_id', $ad->marketplace_client_id)
                ->where('event_id', $ad->event_id)
                ->first();
            $url = $row?->payload['url'] ?? null;

            if (!$url && $ad->event_id) {
                $slug = Event::whereKey($ad->event_id)->value('slug');
                $site = $client ? PartnerEventPresenter::siteUrl($client) : null;
                $url = ($slug && $site) ? $site . '/bilete/' . $slug : null;
            }
        } else {
            $url = $ad->custom_url;
        }

        if (!$url || str_contains($url, 'utm_source=')) {
            return $url ?: null;
        }

        $params = array_filter([
            'utm_source' => Arr::get($ad->partner->settings ?? [], 'utm.source') ?: $ad->partner->slug,
            'utm_medium' => 'display',
            'utm_campaign' => $ad->utm_campaign ?: Str::slug($ad->name),
            'utm_content' => $ad->format,
        ]);

        // Query parameters go before any #fragment.
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params)
            . ($fragment !== null ? '#' . $fragment : '');
    }

    private function queue(PartnerAd $ad, MarketplacePartner $partner, string $type): MarketplacePartnerDelivery
    {
        // Only the latest request for an ad matters; older ones still waiting are dropped.
        MarketplacePartnerDelivery::where('marketplace_partner_id', $partner->id)
            ->whereIn('type', [self::TYPE_UPSERT, self::TYPE_DELETE])
            ->where('subject_id', $ad->id)
            ->where('status', MarketplacePartnerDelivery::STATUS_PENDING)
            ->update([
                'status' => MarketplacePartnerDelivery::STATUS_FAILED,
                'error' => 'Înlocuită de o trimitere mai nouă.',
                'updated_at' => now(),
            ]);

        $upsert = $type === self::TYPE_UPSERT;
        $delivery = $partner->deliveries()->create([
            'type' => $type,
            'subject_id' => $ad->id,
            'method' => $upsert ? 'PUT' : 'DELETE',
            'url' => rtrim((string) $partner->ads_api_url, '/') . '/ads/' . $ad->id,
            'body' => $upsert ? $this->payload($ad) : null,
            'status' => MarketplacePartnerDelivery::STATUS_PENDING,
        ]);

        if ($ad->exists) {
            $ad->forceFill(['sync_status' => PartnerAd::SYNC_PENDING, 'sync_error' => null])->save();
        }

        DeliverPartnerRequestJob::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    /**
     * Whether the partner may have the ad: any request was queued for it and it is not withdrawn.
     */
    private function wasSent(PartnerAd $ad): bool
    {
        return in_array($ad->sync_status, [PartnerAd::SYNC_PENDING, PartnerAd::SYNC_PUBLISHED, PartnerAd::SYNC_FAILED], true);
    }

    /**
     * The partner's own message (e.g. a 422 naming the invalid field) is the useful part.
     */
    private function errorMessage(MarketplacePartnerDelivery $delivery): string
    {
        $body = json_decode((string) $delivery->response_excerpt, true);
        $message = is_array($body) ? ($body['message'] ?? ($body['error']['message'] ?? null)) : null;

        return Str::limit(trim(
            ($delivery->http_status ? 'HTTP ' . $delivery->http_status . ': ' : '')
            . (is_string($message) && $message !== '' ? $message : ($delivery->error ?: 'Eroare necunoscută'))
        ), 500);
    }
}
