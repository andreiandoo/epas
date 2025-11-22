<?php

namespace App\Events;

use App\Models\AutomationEnrollment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerEnrolledInWorkflow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AutomationEnrollment $enrollment) {}
}
