<?php

namespace App\Services\Shorts;

use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortReport;
use App\Services\Video\VideoProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Attendee-submitted shorts (B9).
 *
 * The eligibility rule is the whole feature: you can only post about an event
 * you actually attended, proven by a checked-in ticket you own. That is what
 * makes UGC here a growth loop rather than a spam surface.
 */
class ShortUgcService
{
    /** Uploads one customer may start per event per day. */
    private const DAILY_UPLOAD_LIMIT = 3;

    public function __construct(private readonly VideoProvider $provider) {}

    /**
     * Whether this customer may post a short for this event.
     */
    public function mayPost(MarketplaceCustomer $customer, int $eventId): bool
    {
        if ($this->uploadsToday($customer, $eventId) >= self::DAILY_UPLOAD_LIMIT) {
            return false;
        }

        return $this->hasCheckedInTicket($customer, $eventId);
    }

    /**
     * A ticket for this event, currently owned by this customer, that was
     * actually scanned at the door.
     */
    public function hasCheckedInTicket(MarketplaceCustomer $customer, int $eventId): bool
    {
        try {
            return DB::table('tickets')
                ->where('event_id', $eventId)
                ->where('current_owner_customer_id', $customer->id)
                ->where(fn ($q) => $q->whereNotNull('checked_in_at')->orWhere('checked_in', true))
                ->exists();
        } catch (\Throwable $e) {
            // No way to verify attendance means no permission. Failing open here
            // would turn the feature into an open upload endpoint.
            Log::warning('Shorts UGC: attendance check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Start an upload session for a UGC short.
     *
     * @return array<string, mixed>
     */
    public function createUpload(MarketplaceCustomer $customer, Event $event, ?string $caption = null): array
    {
        $session = $this->provider->createDirectUpload(['title' => $event->title]);

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'video_provider' => $this->provider->name(),
            'provider_asset_id' => $session['asset_id'],
            'owner_type' => Event::class,
            'owner_id' => $event->id,
            'event_id' => $event->id,
            'tenant_id' => $event->tenant_id,
            'caption' => $caption,
            'is_ugc' => true,
            'license_type' => 'ugc',
            'author_marketplace_customer_id' => $customer->id,
            // Never straight to the feed: attendee video is the one category that
            // has to be looked at before it is shown.
            'status' => Short::STATUS_PENDING_REVIEW,
            'ready' => false,
        ]);

        return ['short_id' => $short->id] + $session;
    }

    protected function uploadsToday(MarketplaceCustomer $customer, int $eventId): int
    {
        return Short::query()
            ->where('author_marketplace_customer_id', $customer->id)
            ->where('event_id', $eventId)
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    /**
     * File a viewer report. Repeated reports auto-hide the short pending review —
     * the cost of hiding something good for a few hours is far lower than the
     * cost of leaving something harmful up.
     */
    public function report(Short $short, ?MarketplaceCustomer $customer, string $reason, ?string $detail = null): ShortReport
    {
        $report = ShortReport::create([
            'short_id' => $short->id,
            'marketplace_customer_id' => $customer?->id,
            'reason' => in_array($reason, ShortReport::REASONS, true) ? $reason : 'other',
            'detail' => $detail,
        ]);

        Short::query()->whereKey($short->id)->increment('reports_count');

        $threshold = (int) config('shorts.moderation.auto_hide_reports', 3);
        $count = (int) Short::query()->whereKey($short->id)->value('reports_count');

        if ($count >= $threshold && $short->status === Short::STATUS_PUBLISHED) {
            Short::query()->whereKey($short->id)->update(['status' => Short::STATUS_PENDING_REVIEW]);

            Log::info('Shorts: auto-hid a reported short pending review', [
                'short_id' => $short->id,
                'reports' => $count,
            ]);
        }

        return $report;
    }
}
