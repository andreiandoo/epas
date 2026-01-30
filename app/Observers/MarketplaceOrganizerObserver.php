<?php

namespace App\Observers;

use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxTemplate;
use App\Models\MarketplaceTaxRegistry;
use App\Models\OrganizerDocument;
use App\Services\MarketplaceNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MarketplaceOrganizerObserver
{
    public function __construct(
        protected MarketplaceNotificationService $notificationService
    ) {}

    /**
     * Handle the MarketplaceOrganizer "created" event.
     */
    public function created(MarketplaceOrganizer $organizer): void
    {
        // Notify about new organizer registration
        if ($organizer->marketplace_client_id) {
            try {
                $this->notificationService->notifyOrganizerRegistration(
                    $organizer->marketplace_client_id,
                    $organizer->name ?? $organizer->email,
                    $organizer->company_name,
                    $organizer,
                    route('filament.marketplace.resources.organizers.edit', ['record' => $organizer->id])
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create organizer registration notification', [
                    'organizer_id' => $organizer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the MarketplaceOrganizer "updated" event.
     */
    public function updated(MarketplaceOrganizer $organizer): void
    {
        // Check if verified_at changed from null to a value
        if ($organizer->isDirty('verified_at') && $organizer->verified_at !== null && $organizer->getOriginal('verified_at') === null) {
            $this->generateOrganizerContract($organizer);
        }
    }

    /**
     * Generate organizer contract when verified
     */
    protected function generateOrganizerContract(MarketplaceOrganizer $organizer): void
    {
        try {
            $marketplace = $organizer->marketplaceClient;

            if (!$marketplace) {
                Log::warning('MarketplaceOrganizerObserver: No marketplace client found for organizer', ['organizer_id' => $organizer->id]);
                return;
            }

            // Check if contract already exists
            $existingContract = OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->where('document_type', 'organizer_contract')
                ->first();

            if ($existingContract) {
                Log::info('MarketplaceOrganizerObserver: Contract already exists for organizer', ['organizer_id' => $organizer->id]);
                return;
            }

            // Get template for organizer contract
            $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                ->where('type', 'organizer_contract')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning('MarketplaceOrganizerObserver: No organizer contract template found', [
                    'organizer_id' => $organizer->id,
                    'marketplace_id' => $marketplace->id,
                ]);
                return;
            }

            // Get tax registry for the marketplace
            $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where('is_active', true)
                ->first();

            // Get template variables (without event)
            $variables = MarketplaceTaxTemplate::getVariablesForContext(
                $taxRegistry,
                $marketplace,
                $organizer,
                null // No event for organizer contract
            );

            // Process template
            $htmlContent = $template->processTemplate($variables);

            // Ensure proper UTF-8 encoding for diacritics
            if (stripos($htmlContent, '<html') === false) {
                $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
    </style>
</head>
<body>' . $htmlContent . '</body>
</html>';
            } else {
                if (stripos($htmlContent, 'charset') === false) {
                    $htmlContent = preg_replace(
                        '/<head>/i',
                        '<head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>',
                        $htmlContent
                    );
                }
                if (stripos($htmlContent, 'font-family') === false) {
                    $htmlContent = preg_replace(
                        '/<\/head>/i',
                        '<style>body { font-family: DejaVu Sans, sans-serif; }</style></head>',
                        $htmlContent
                    );
                }
            }

            // Generate PDF
            $pdf = Pdf::loadHTML($htmlContent);

            // Set orientation based on template settings
            if ($template->page_orientation === 'landscape') {
                $pdf->setPaper('A4', 'landscape');
            } else {
                $pdf->setPaper('A4', 'portrait');
            }

            $pdfContent = $pdf->output();

            // Generate unique filename
            $fileName = sprintf(
                'organizer_contract_%s_%s.pdf',
                $organizer->id,
                now()->format('YmdHis')
            );

            $filePath = sprintf(
                'organizer-documents/%d/%s',
                $organizer->id,
                $fileName
            );

            // Save to storage
            Storage::disk('public')->put($filePath, $pdfContent);

            // Create document record
            OrganizerDocument::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer->id,
                'event_id' => null, // No event for organizer contract
                'tax_template_id' => $template->id,
                'title' => $template->name,
                'document_type' => 'organizer_contract',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => strlen($pdfContent),
                'html_content' => $htmlContent,
                'document_data' => [
                    'organizer_name' => $organizer->company_name ?? $organizer->name,
                    'template_name' => $template->name,
                    'variables' => $variables,
                    'generated_on_verification' => true,
                ],
                'issued_at' => now(),
            ]);

            Log::info('MarketplaceOrganizerObserver: Organizer contract generated successfully', [
                'organizer_id' => $organizer->id,
                'document_path' => $filePath,
            ]);

            // Send notification about document generation
            try {
                $this->notificationService->notifyDocumentGenerated(
                    $marketplace->id,
                    'organizer_contract',
                    $organizer->company_name ?? $organizer->name,
                    null,
                    route('filament.marketplace.resources.organizers.edit', ['record' => $organizer->id])
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create document notification', ['error' => $e->getMessage()]);
            }

        } catch (\Exception $e) {
            Log::error('MarketplaceOrganizerObserver: Failed to generate organizer contract', [
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
