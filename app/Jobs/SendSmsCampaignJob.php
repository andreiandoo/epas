<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\SmsCampaign;
use App\Models\SmsCredit;
use App\Services\Sms\SendSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour max

    public function __construct(protected int $campaignId) {}

    public function handle(SendSmsService $smsService): void
    {
        $campaign = SmsCampaign::find($this->campaignId);
        if (!$campaign || !in_array($campaign->status, ['scheduled', 'sending'])) {
            return;
        }

        $client = MarketplaceClient::find($campaign->marketplace_client_id);
        if (!$client) {
            $campaign->update(['status' => 'failed']);
            return;
        }

        $campaign->update(['status' => 'sending']);

        $smsPerRecipient = SmsCampaign::calculateSmsCount($campaign->message_text);
        $availableCredits = SmsCredit::getAvailableCredits($client, 'promotional');

        // Build audience query
        $recipients = $this->buildAudienceQuery($campaign)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $sent = 0;
        $failed = 0;
        $totalCost = 0;

        foreach ($recipients as $customer) {
            // Check credits for each SMS (smsPerRecipient credits needed per person)
            $creditsNeeded = $smsPerRecipient;
            $currentCredits = SmsCredit::getAvailableCredits($client, 'promotional');

            if ($currentCredits < $creditsNeeded) {
                Log::channel('marketplace')->warning('SMS campaign ran out of credits', [
                    'campaign_id' => $campaign->id,
                    'sent_so_far' => $sent,
                ]);
                break;
            }

            // Consume credits
            for ($i = 0; $i < $creditsNeeded; $i++) {
                SmsCredit::consumeCredit($client, 'promotional');
            }

            $result = $smsService->sendSms($customer->phone, $campaign->message_text, [
                'type' => 'promotional',
                'marketplace_client_id' => $client->id,
                'event_id' => $campaign->event_id,
            ]);

            if (($result['status'] ?? -1) >= 1) {
                $sent++;
            } else {
                $failed++;
            }

            $costPerSms = $smsService->getSmsCostPublic('promotional', $client->id);
            $totalCost += $costPerSms * $smsPerRecipient;
        }

        $campaign->update([
            'status' => 'sent',
            'sms_sent' => $sent,
            'sms_failed' => $failed,
            'total_cost' => $totalCost,
            'sent_at' => now(),
        ]);
    }

    protected function buildAudienceQuery(SmsCampaign $campaign)
    {
        $query = MarketplaceCustomer::where('marketplace_client_id', $campaign->marketplace_client_id)
            ->where('status', 'active');

        $filters = $campaign->filters ?? [];

        // City filter
        if (!empty($filters['city_ids'])) {
            $query->where(function ($q) use ($filters, $campaign) {
                // Match by customer city name against marketplace city names
                $cityNames = \App\Models\MarketplaceCity::whereIn('id', $filters['city_ids'])
                    ->get()
                    ->flatMap(fn ($city) => array_values(is_array($city->name) ? $city->name : [$city->name]))
                    ->map(fn ($name) => strtolower(trim($name)))
                    ->unique()
                    ->toArray();

                if (!empty($cityNames)) {
                    $q->where(function ($qq) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $qq->orWhereRaw('LOWER(TRIM(city)) = ?', [$name]);
                        }
                    });
                }

                // Also include customers who attended events in these cities
                $q->orWhereHas('orders', function ($oq) use ($filters) {
                    $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                        ->whereHas('event', function ($eq) use ($filters) {
                            $eq->whereIn('marketplace_city_id', $filters['city_ids']);
                        });
                });
            });
        }

        // Artist filter
        if (!empty($filters['artist_ids'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('orders', function ($oq) use ($filters) {
                    $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                        ->whereHas('event', function ($eq) use ($filters) {
                            $eq->whereHas('artists', function ($aq) use ($filters) {
                                $aq->whereIn('artists.id', $filters['artist_ids']);
                            });
                        });
                });
                // Also check favorite artists
                $q->orWhereHas('favoriteArtists', function ($fq) use ($filters) {
                    $fq->whereIn('artists.id', $filters['artist_ids']);
                });
            });
        }

        // Genre filter
        if (!empty($filters['genre_ids'])) {
            $query->whereHas('orders', function ($oq) use ($filters) {
                $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                    ->whereHas('event', function ($eq) use ($filters) {
                        $eq->whereHas('eventGenres', function ($gq) use ($filters) {
                            $gq->whereIn('event_genres.id', $filters['genre_ids']);
                        });
                    });
            });
        }

        // Venue filter
        if (!empty($filters['venue_ids'])) {
            $query->whereHas('orders', function ($oq) use ($filters) {
                $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                    ->whereHas('event', function ($eq) use ($filters) {
                        $eq->whereIn('venue_id', $filters['venue_ids']);
                    });
            });
        }

        return $query;
    }

    public function failed(\Throwable $exception): void
    {
        $campaign = SmsCampaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update(['status' => 'failed']);
        }

        Log::channel('marketplace')->error('SendSmsCampaignJob failed', [
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);
    }
}
