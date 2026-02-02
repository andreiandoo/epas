<?php

namespace App\Jobs;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendMarketplaceOrganizerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $marketplaceClientId,
        public int $organizerId,
        public string $subject,
        public string $body,
        public ?string $replyTo = null
    ) {}

    public function handle(): void
    {
        $marketplace = MarketplaceClient::find($this->marketplaceClientId);
        $organizer = MarketplaceOrganizer::find($this->organizerId);

        if (!$marketplace || !$organizer) {
            Log::warning('SendMarketplaceOrganizerEmailJob: Missing marketplace or organizer', [
                'marketplace_id' => $this->marketplaceClientId,
                'organizer_id' => $this->organizerId,
            ]);
            return;
        }

        if (!$marketplace->hasMailConfigured()) {
            Log::warning('SendMarketplaceOrganizerEmailJob: Mail not configured for marketplace', [
                'marketplace_id' => $marketplace->id,
                'marketplace_name' => $marketplace->name,
            ]);
            return;
        }

        try {
            $transport = $marketplace->getMailTransport();

            if (!$transport) {
                Log::error('SendMarketplaceOrganizerEmailJob: Could not create mail transport');
                return;
            }

            // Process template variables in subject and body
            $variables = $this->getTemplateVariables($marketplace, $organizer);
            $processedSubject = $this->processTemplate($this->subject, $variables);
            $processedBody = $this->processTemplate($this->body, $variables);

            $email = (new Email())
                ->from(new Address($marketplace->getEmailFromAddress(), $marketplace->getEmailFromName()))
                ->to($organizer->email)
                ->subject($processedSubject)
                ->html($processedBody);

            if ($this->replyTo) {
                $email->replyTo($this->replyTo);
            }

            $transport->send($email);

            Log::info('SendMarketplaceOrganizerEmailJob: Email sent successfully', [
                'organizer_id' => $organizer->id,
                'organizer_email' => $organizer->email,
                'subject' => $processedSubject,
            ]);

        } catch (\Exception $e) {
            Log::error('SendMarketplaceOrganizerEmailJob: Failed to send email', [
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Get template variables for email processing
     */
    protected function getTemplateVariables(MarketplaceClient $marketplace, MarketplaceOrganizer $organizer): array
    {
        return [
            'organizer_name' => $organizer->name ?? '',
            'organizer_email' => $organizer->email ?? '',
            'company_name' => $organizer->company_name ?? '',
            'contact_name' => $organizer->contact_name ?? '',
            'marketplace_name' => $marketplace->name ?? '',
            'marketplace_domain' => $marketplace->domain ?? '',
            'settings_url' => ($marketplace->domain ?? '') . '/organizator/setari',
            'contract_url' => ($marketplace->domain ?? '') . '/organizator/setari#contract',
            'dashboard_url' => ($marketplace->domain ?? '') . '/organizator/dashboard',
            'login_url' => ($marketplace->domain ?? '') . '/organizator/login',
            'support_email' => $marketplace->contact_email ?? '',
            'current_date' => now()->format('d.m.Y'),
            'current_year' => now()->format('Y'),
        ];
    }

    /**
     * Process template variables in text
     */
    protected function processTemplate(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value ?? '', $text);
        }
        return $text;
    }
}
