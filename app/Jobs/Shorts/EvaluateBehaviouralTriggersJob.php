<?php

namespace App\Jobs\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\NotificationPreference;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Push\PushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Behavioural re-engagement nudges from shorts activity (D12).
 *
 * The trigger implemented here is the strongest one: someone watched several
 * shorts for the same event and did not buy. Everything about it is opt-in,
 * frequency-capped and quiet-hours-aware, because a nudge based on watching
 * behaviour is the one most likely to feel like surveillance.
 *
 * TODO(owner): the plan routes these through AutomationWorkflow/AutomationStep so
 * marketing can edit copy and cadence without a deploy. That enrolment is not
 * wired here — the trigger evaluation and the guardrails are, and they are what
 * needs to be right. Point send() at an enrolment when the workflow templates exist.
 */
class EvaluateBehaviouralTriggersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** How many shorts for one event count as real intent. */
    private const INTENT_THRESHOLD = 3;

    /** Never nudge the same person about the same event twice inside this window. */
    private const COOLDOWN_DAYS = 14;

    /** Local hours during which nothing is sent. */
    private const QUIET_FROM = 22;

    private const QUIET_TO = 9;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(protected int $lookbackHours = 48) {}

    public function handle(PushSender $push): void
    {
        if ($this->inQuietHours()) {
            return;
        }

        $sent = 0;

        foreach ($this->intentSignals() as $signal) {
            $customerId = (int) $signal->marketplace_customer_id;
            $eventId = (int) $signal->event_id;

            if (! NotificationPreference::allows($customerId, NotificationPreference::TYPE_SHORTS_ABANDONED)) {
                continue;
            }

            if ($this->alreadyBought($customerId, $eventId) || $this->recentlyNudged($customerId, $eventId)) {
                continue;
            }

            $customer = MarketplaceCustomer::find($customerId);

            if (! $customer) {
                continue;
            }

            $eventTitle = Short::query()->where('event_id', $eventId)->value('title') ?? 'evenimentul urmărit';

            $push->send(
                $customer,
                'Încă te mai gândești?',
                "Ai văzut mai multe clipuri de la {$eventTitle}. Biletele se vând repede.",
                ['type' => NotificationPreference::TYPE_SHORTS_ABANDONED, 'event_id' => $eventId],
            );

            $this->markNudged($customerId, $eventId);
            $sent++;
        }

        if ($sent > 0) {
            Log::info('EvaluateBehaviouralTriggersJob: sent nudges', [
                'sent' => $sent,
                'push_configured' => $push->isConfigured(),
            ]);
        }
    }

    /**
     * Viewers who watched at least INTENT_THRESHOLD distinct shorts for one event.
     *
     * @return Collection<int, object>
     */
    protected function intentSignals(): Collection
    {
        return ShortEvent::query()
            ->join('shorts', 'shorts.id', '=', 'short_events.short_id')
            ->selectRaw('short_events.marketplace_customer_id, shorts.event_id, COUNT(DISTINCT short_events.short_id) as watched')
            ->whereNotNull('short_events.marketplace_customer_id')
            ->whereNotNull('shorts.event_id')
            ->where('short_events.created_at', '>=', now()->subHours($this->lookbackHours))
            ->whereIn('short_events.type', [ShortEvent::TYPE_VIEW, ShortEvent::TYPE_COMPLETE])
            ->groupBy('short_events.marketplace_customer_id', 'shorts.event_id')
            ->havingRaw('COUNT(DISTINCT short_events.short_id) >= ?', [self::INTENT_THRESHOLD])
            ->get();
    }

    protected function alreadyBought(int $customerId, int $eventId): bool
    {
        try {
            return DB::table('orders')
                ->where('marketplace_customer_id', $customerId)
                ->where('event_id', $eventId)
                ->exists();
        } catch (\Throwable) {
            // Cannot verify — say yes, so an uncertain state never produces a
            // nudge telling someone to buy what they already own.
            return true;
        }
    }

    protected function recentlyNudged(int $customerId, int $eventId): bool
    {
        return cache()->has($this->cooldownKey($customerId, $eventId));
    }

    protected function markNudged(int $customerId, int $eventId): void
    {
        cache()->put($this->cooldownKey($customerId, $eventId), true, now()->addDays(self::COOLDOWN_DAYS));
    }

    protected function cooldownKey(int $customerId, int $eventId): string
    {
        return "shorts:nudge:{$customerId}:{$eventId}";
    }

    /**
     * TODO(owner): uses the app timezone, not the customer's. Per-recipient quiet
     * hours need a timezone on marketplace_customers, which does not exist yet.
     */
    protected function inQuietHours(): bool
    {
        $hour = (int) now()->format('G');

        return $hour >= self::QUIET_FROM || $hour < self::QUIET_TO;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('EvaluateBehaviouralTriggersJob failed', ['error' => $e->getMessage()]);
    }
}
