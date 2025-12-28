<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public CampaignRecipient $recipient) {}

    public function handle(): void
    {
        $campaign = $this->recipient->campaign;

        // Add tracking pixels and links
        $content = $this->addTracking($campaign->content, $this->recipient->id);

        // Send email
        // Mail::send(...);

        $this->recipient->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    protected function addTracking(string $content, int $recipientId): string
    {
        // Add open tracking pixel
        $pixel = '<img src="' . route('crm.track.open', $recipientId) . '" width="1" height="1" />';
        $content = str_replace('</body>', $pixel . '</body>', $content);

        // Replace links with tracked versions
        // ...

        return $content;
    }
}
