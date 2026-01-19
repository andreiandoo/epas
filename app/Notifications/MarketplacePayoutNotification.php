<?php

namespace App\Notifications;

use App\Models\MarketplacePayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplacePayoutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MarketplacePayout $payout,
        public string $action
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->action) {
            'submitted' => $this->submittedMail($notifiable),
            'approved' => $this->approvedMail($notifiable),
            'processing' => $this->processingMail($notifiable),
            'completed' => $this->completedMail($notifiable),
            'rejected' => $this->rejectedMail($notifiable),
            default => $this->defaultMail($notifiable),
        };
    }

    protected function submittedMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout Request Submitted - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payout request has been submitted successfully.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Amount:** {$this->payout->amount} {$this->payout->currency}")
            ->line("**Period:** {$this->payout->period_start->format('d M Y')} - {$this->payout->period_end->format('d M Y')}")
            ->line("We will review your request and process it as soon as possible.")
            ->line("You will receive a notification when your payout is approved or if we need additional information.");
    }

    protected function approvedMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout Approved - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Great news! Your payout request has been approved.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Amount:** {$this->payout->amount} {$this->payout->currency}")
            ->line("Your payment will be processed shortly. You will receive another notification when the transfer is complete.");
    }

    protected function processingMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout Processing - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payout is now being processed.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Amount:** {$this->payout->amount} {$this->payout->currency}")
            ->line("The transfer has been initiated. Please allow 1-3 business days for the funds to appear in your account.");
    }

    protected function completedMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Payout Completed - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payout has been completed successfully!")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Amount:** {$this->payout->amount} {$this->payout->currency}");

        if ($this->payout->payment_reference) {
            $mail->line("**Payment Reference:** {$this->payout->payment_reference}");
        }

        return $mail->line("The funds should now be available in your bank account. Thank you for your partnership!");
    }

    protected function rejectedMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout Rejected - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Unfortunately, your payout request has been rejected.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Amount:** {$this->payout->amount} {$this->payout->currency}")
            ->line("**Reason:** {$this->payout->rejection_reason}")
            ->line("The requested amount has been returned to your available balance. If you have questions, please contact support.");
    }

    protected function defaultMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payout Update - {$this->payout->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line("There's an update to your payout request.")
            ->line("**Reference:** {$this->payout->reference}")
            ->line("**Status:** {$this->payout->status_label}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payout_id' => $this->payout->id,
            'reference' => $this->payout->reference,
            'amount' => $this->payout->amount,
            'status' => $this->payout->status,
            'action' => $this->action,
        ];
    }
}
