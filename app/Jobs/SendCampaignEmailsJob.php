<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\CampaignRecipient;
use App\Events\CampaignSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public EmailCampaign $campaign) {}

    public function handle(): void
    {
        $this->campaign->update(['status' => 'sending']);

        $sent = 0;
        $recipients = $this->campaign->recipients()
            ->where('status', 'pending')
            ->cursor();

        foreach ($recipients as $recipient) {
            // Queue individual email
            SendCampaignEmailJob::dispatch($recipient);
            $sent++;
        }

        $this->campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sent,
        ]);

        event(new CampaignSent($this->campaign));
    }
}
