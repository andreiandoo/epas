<?php

namespace App\Notifications;

use App\Models\Affiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAffiliateSignupNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Affiliate $affiliate
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('New Affiliate Application: :name', ['name' => $this->affiliate->name]))
            ->line(__('A new affiliate application has been submitted.'))
            ->line(__('**Name:** :name', ['name' => $this->affiliate->name]))
            ->line(__('**Email:** :email', ['email' => $this->affiliate->contact_email]));

        if ($this->affiliate->status === 'pending') {
            $message->line(__('This application requires your approval.'))
                ->action(__('Review Application'), url('/tenant/' . $this->affiliate->tenant->slug . '/affiliates/' . $this->affiliate->id . '/edit'));
        } else {
            $message->line(__('The affiliate account has been automatically activated.'));
        }

        return $message;
    }
}
