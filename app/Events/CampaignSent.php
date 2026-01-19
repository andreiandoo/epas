<?php

namespace App\Events;

use App\Models\EmailCampaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EmailCampaign $campaign) {}
}
