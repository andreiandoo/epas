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
        // Only mail - database notifications are handled via MarketplaceNotification model
        return ['marketplace-mail'];
    }

    /**
     * Slug picked up by MarketplaceMailChannel to render via the
     * marketplace's DB-stored email template (per-marketplace branding).
     * Returning a slug doesn't bypass toMail() — if the template row is
     * missing for this marketplace, the channel falls back to toMail()
     * and the legacy MailMessage body is sent.
     */
    public function marketplaceTemplateSlug(object $notifiable): ?string
    {
        return match ($this->action) {
            'submitted' => 'payout_submitted',
            'approved' => 'payout_approved',
            'processing' => 'payout_processing',
            'completed' => 'payout_completed',
            'rejected' => 'payout_rejected',
            default => null,
        };
    }

    /**
     * Variables injected into the template's {{placeholders}}. The channel
     * already adds marketplace_name / customer_name / customer_email; we
     * only need to add the payout-specific fields here.
     */
    public function marketplaceTemplateData(object $notifiable): array
    {
        $amount = number_format((float) $this->payout->amount, 2, ',', '.') . ' ' . ($this->payout->currency ?? 'EUR');
        $period = '';
        if ($this->payout->period_start && $this->payout->period_end) {
            $period = $this->payout->period_start->format('d M Y') . ' — ' . $this->payout->period_end->format('d M Y');
        }

        return [
            'organizer_name' => $notifiable->name ?? trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? '')),
            'payout_reference' => (string) ($this->payout->reference ?? '-'),
            'payout_amount' => $amount,
            'payout_period' => $period ?: '-',
            'payment_reference' => (string) ($this->payout->payment_reference ?? '-'),
            'rejection_reason' => (string) ($this->payout->rejection_reason ?? '-'),
        ];
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
        $data = [
            'payout_id' => $this->payout->id,
            'reference' => $this->payout->reference,
            'amount' => $this->payout->amount,
            'currency' => $this->payout->currency,
            'status' => $this->payout->status,
            'action' => $this->action,
            'title' => $this->getNotificationTitle(),
            'message' => $this->getNotificationMessage(),
        ];

        if ($this->action === 'rejected' && $this->payout->rejection_reason) {
            $data['rejection_reason'] = $this->payout->rejection_reason;
        }

        return $data;
    }

    protected function getNotificationTitle(): string
    {
        return match ($this->action) {
            'submitted' => 'Cerere de plată înregistrată',
            'approved' => 'Cerere de plată aprobată',
            'processing' => 'Plată în procesare',
            'completed' => 'Plată finalizată',
            'rejected' => 'Cerere de plată respinsă',
            default => 'Actualizare plată',
        };
    }

    protected function getNotificationMessage(): string
    {
        $amount = number_format($this->payout->amount, 2) . ' ' . $this->payout->currency;

        return match ($this->action) {
            'submitted' => "Cererea de plată {$this->payout->reference} în valoare de {$amount} a fost înregistrată.",
            'approved' => "Cererea de plată {$this->payout->reference} în valoare de {$amount} a fost aprobată.",
            'processing' => "Plata {$this->payout->reference} în valoare de {$amount} este în curs de procesare.",
            'completed' => "Plata {$this->payout->reference} în valoare de {$amount} a fost finalizată.",
            'rejected' => "Cererea de plată {$this->payout->reference} în valoare de {$amount} a fost respinsă.",
            default => "Actualizare pentru plata {$this->payout->reference}.",
        };
    }
}
