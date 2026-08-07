<?php

namespace App\Jobs\Shorts;

use App\Models\ShortReminder;
use App\Services\Push\PushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fires the "tickets are live" notifications for due reminders (D2).
 *
 * Scheduled every minute: a drop is a moment, and a reminder that arrives an
 * hour late is worse than none — it arrives after the good tickets are gone.
 *
 * notified_at is stamped whether or not the transport is configured. Without a
 * real push layer the message is logged (see PushSender); re-sending it later
 * would be worse than missing it, because by then the drop is old news.
 */
class FireDropRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 300;

    public function __construct(protected int $limit = 500) {}

    public function handle(PushSender $push): void
    {
        $due = ShortReminder::query()
            ->due()
            ->with(['customer', 'short.event'])
            ->limit($this->limit)
            ->get();

        if ($due->isEmpty()) {
            return;
        }

        $sent = 0;

        foreach ($due as $reminder) {
            $customer = $reminder->customer;

            if (! $customer) {
                // Account gone — the row would otherwise be scanned forever.
                $reminder->forceFill(['notified_at' => now()])->save();

                continue;
            }

            $eventName = $reminder->short?->event?->title
                ?? $reminder->short?->title
                ?? 'evenimentul urmărit';

            $push->send(
                $customer,
                'Biletele sunt live 🎟',
                "Biletele la {$eventName} tocmai au intrat în vânzare.",
                [
                    'type' => 'short_drop',
                    'short_id' => $reminder->short_id,
                    'event_id' => $reminder->event_id,
                ],
            );

            $reminder->forceFill(['notified_at' => now()])->save();
            $sent++;
        }

        Log::info('FireDropRemindersJob: fired drop reminders', [
            'sent' => $sent,
            'push_configured' => $push->isConfigured(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FireDropRemindersJob failed', ['error' => $e->getMessage()]);
    }
}
