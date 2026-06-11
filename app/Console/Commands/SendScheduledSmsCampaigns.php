<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsCampaignJob;
use App\Models\SmsCampaign;
use Illuminate\Console\Command;

class SendScheduledSmsCampaigns extends Command
{
    protected $signature = 'sms:send-scheduled-campaigns';
    protected $description = 'Dispatch scheduled SMS campaigns that are due';

    public function handle(): int
    {
        $campaigns = SmsCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            SendSmsCampaignJob::dispatch($campaign->id);
            $this->info("Dispatched campaign #{$campaign->id}: {$campaign->name}");
        }

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled campaigns due.');
        }

        return self::SUCCESS;
    }
}
