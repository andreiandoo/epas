<?php

namespace App\Mail;

use App\Models\EventGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoalAlertNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public EventGoal $goal;
    public array $alertData;

    public function __construct(EventGoal $goal, array $alertData)
    {
        $this->goal = $goal;
        $this->alertData = $alertData;
    }

    public function envelope(): Envelope
    {
        $threshold = $this->alertData['threshold'];
        $eventName = $this->alertData['event_name'];

        $subject = $threshold >= 100
            ? "Goal Achieved! {$eventName}"
            : "{$threshold}% of goal reached - {$eventName}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.analytics.goal-alert',
            with: [
                'goal' => $this->goal,
                'data' => $this->alertData,
            ],
        );
    }
}
