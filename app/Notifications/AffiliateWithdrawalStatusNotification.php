<?php

namespace App\Notifications;

use App\Models\AffiliateWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateWithdrawalStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AffiliateWithdrawal $withdrawal,
        public string $previousStatus
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $affiliate = $this->withdrawal->affiliate;
        $amount = number_format($this->withdrawal->amount, 2) . ' ' . ($this->withdrawal->currency ?? 'RON');
        $status = $this->withdrawal->status;

        $message = (new MailMessage)
            ->greeting("Hello {$affiliate->name}!");

        switch ($status) {
            case 'processing':
                $message
                    ->subject("Withdrawal Request Being Processed - {$amount}")
                    ->line("Your withdrawal request for **{$amount}** is now being processed.")
                    ->line("Reference: **{$this->withdrawal->reference}**")
                    ->line("We'll notify you once the payment has been completed.");
                break;

            case 'completed':
                $message
                    ->subject("Withdrawal Completed - {$amount}")
                    ->line("Great news! Your withdrawal of **{$amount}** has been completed.")
                    ->line("Reference: **{$this->withdrawal->reference}**")
                    ->when($this->withdrawal->transaction_id, fn ($m) => $m
                        ->line("Transaction ID: **{$this->withdrawal->transaction_id}**")
                    )
                    ->line("The funds have been sent to your registered payment method.")
                    ->line("Please allow 1-3 business days for the funds to appear in your account.");
                break;

            case 'rejected':
                $message
                    ->subject("Withdrawal Request Rejected - {$amount}")
                    ->line("Unfortunately, your withdrawal request for **{$amount}** has been rejected.")
                    ->line("Reference: **{$this->withdrawal->reference}**")
                    ->when($this->withdrawal->rejection_reason, fn ($m) => $m
                        ->line("Reason: {$this->withdrawal->rejection_reason}")
                    )
                    ->line("The amount has been returned to your available balance.")
                    ->line("If you believe this is an error, please contact support.");
                break;

            default:
                $message
                    ->subject("Withdrawal Status Update - {$amount}")
                    ->line("Your withdrawal request status has been updated.")
                    ->line("Reference: **{$this->withdrawal->reference}**")
                    ->line("Amount: **{$amount}**")
                    ->line("New Status: **" . ucfirst($status) . "**");
        }

        return $message;
    }
}
