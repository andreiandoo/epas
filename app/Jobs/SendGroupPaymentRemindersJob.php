<?php

namespace App\Jobs;

use App\Models\GroupBookingMember;
use App\Notifications\GroupPaymentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGroupPaymentRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $pendingMembers = GroupBookingMember::where('payment_status', 'pending')
            ->whereHas('groupBooking', function ($q) {
                $q->where('status', 'pending')
                  ->where('payment_type', 'split');
            })
            ->with('groupBooking.event')
            ->get();

        foreach ($pendingMembers as $member) {
            if ($member->email) {
                $member->notify(new GroupPaymentReminderNotification($member));
            }
        }
    }
}
