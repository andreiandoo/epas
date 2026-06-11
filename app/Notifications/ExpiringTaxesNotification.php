<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiringTaxesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $expiringTaxes,
        public int $daysAhead
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $count = $this->expiringTaxes['total'];
        $generalCount = $this->expiringTaxes['general']->count();
        $localCount = $this->expiringTaxes['local']->count();

        $message = (new MailMessage)
            ->subject("Tax Expiration Notice: {$count} Tax(es) Expiring Soon")
            ->greeting('Tax Expiration Alert')
            ->line("You have {$count} tax configuration(s) expiring in the next {$this->daysAhead} days.");

        if ($generalCount > 0) {
            $message->line("**General Taxes ({$generalCount}):**");
            foreach ($this->expiringTaxes['general']->take(5) as $tax) {
                $message->line("- {$tax->name}: expires {$tax->valid_until->format('M j, Y')}");
            }
            if ($generalCount > 5) {
                $message->line("... and " . ($generalCount - 5) . " more");
            }
        }

        if ($localCount > 0) {
            $message->line("**Local Taxes ({$localCount}):**");
            foreach ($this->expiringTaxes['local']->take(5) as $tax) {
                $location = $tax->getLocationString();
                $message->line("- {$location}: expires {$tax->valid_until->format('M j, Y')}");
            }
            if ($localCount > 5) {
                $message->line("... and " . ($localCount - 5) . " more");
            }
        }

        return $message
            ->action('Review Tax Settings', url('/admin/taxes'))
            ->line('Please review and update these tax configurations to ensure accurate calculations.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'expiring_taxes',
            'total' => $this->expiringTaxes['total'],
            'general_count' => $this->expiringTaxes['general']->count(),
            'local_count' => $this->expiringTaxes['local']->count(),
            'days_ahead' => $this->daysAhead,
            'message' => "{$this->expiringTaxes['total']} tax(es) expiring in the next {$this->daysAhead} days",
        ];
    }
}
