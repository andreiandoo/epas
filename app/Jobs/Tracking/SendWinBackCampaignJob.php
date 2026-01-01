<?php

namespace App\Jobs\Tracking;

use App\Services\Tracking\WinBackCampaignService;
use App\Services\Tracking\RecommendationService;
use App\Models\Tenant;
use App\Mail\WinBackEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendWinBackCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        protected int $tenantId,
        protected string $tier = 'all',
        protected int $limit = 100
    ) {
        $this->queue = 'emails';
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            Log::warning("SendWinBackCampaignJob: Tenant not found", ['tenant_id' => $this->tenantId]);
            return;
        }

        $service = WinBackCampaignService::forTenant($this->tenantId);
        $candidates = $service->identifyWinBackCandidates();

        $tiers = $this->tier === 'all'
            ? ['early_warning', 'gentle_nudge', 'win_back', 'last_chance']
            : [$this->tier];

        $campaignId = 'winback_auto_' . now()->format('Ymd_His');
        $sentCount = 0;
        $errorCount = 0;

        foreach ($tiers as $tier) {
            $tierCandidates = array_slice($candidates['candidates'][$tier] ?? [], 0, $this->limit);

            foreach ($tierCandidates as $candidate) {
                try {
                    $this->sendWinBackEmail($tenant, $candidate, $tier, $campaignId);
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::warning("Failed to send win-back email", [
                        'tenant_id' => $this->tenantId,
                        'person_id' => $candidate['person_id'],
                        'tier' => $tier,
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            // Mark as contacted
            if (!empty($tierCandidates)) {
                $personIds = array_column($tierCandidates, 'person_id');
                $service->markAsContacted($personIds, $tier, $campaignId);
            }
        }

        Log::info("WinBack campaign completed", [
            'tenant_id' => $this->tenantId,
            'campaign_id' => $campaignId,
            'sent' => $sentCount,
            'errors' => $errorCount,
        ]);
    }

    protected function sendWinBackEmail(Tenant $tenant, array $candidate, string $tier, string $campaignId): void
    {
        // Get customer email
        $customer = DB::table('core_customers')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $candidate['person_id'])
            ->first();

        if (!$customer || empty($customer->email)) {
            return;
        }

        // Get recommendations for personalization
        $recommendations = [];
        try {
            $recService = RecommendationService::for($this->tenantId, $candidate['person_id']);
            $recs = $recService->getEventRecommendations(3);
            $recommendations = $recs['recommendations'] ?? [];
        } catch (\Exception $e) {
            // Continue without recommendations
        }

        // Build email data
        $emailData = [
            'tenant' => $tenant,
            'customer' => $customer,
            'tier' => $tier,
            'offer' => $candidate['offer'] ?? null,
            'recommendations' => $recommendations,
            'campaign_id' => $campaignId,
            'preferences' => $candidate['preferences'] ?? [],
        ];

        // Send email (using a Mailable class)
        // In production, this would use a proper email template
        Mail::to($customer->email)->queue(new WinBackEmail($emailData));
    }
}
