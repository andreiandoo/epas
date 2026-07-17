<?php

namespace App\Services;

use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxTemplate;
use App\Models\MarketplaceTaxRegistry;
use App\Models\OrganizerDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Generates the organizer contract PDF from the marketplace's
 * `organizer_contract` tax template and stores it as an OrganizerDocument.
 *
 * Extracted from MarketplaceOrganizerObserver so the same logic can run both
 * when an admin verifies an organizer AND at the end of self-service
 * onboarding (see AuthController::register). Idempotent: if a contract already
 * exists for the organizer it is a no-op unless $force is true.
 */
class OrganizerContractService
{
    public function __construct(
        protected ?MarketplaceNotificationService $notificationService = null
    ) {
        $this->notificationService ??= app(MarketplaceNotificationService::class);
    }

    /**
     * Generate (or return the existing) organizer contract document.
     *
     * @return OrganizerDocument|null The created/existing document, or null when
     *         no template/marketplace is available or generation failed.
     */
    public function generate(MarketplaceOrganizer $organizer, bool $force = false): ?OrganizerDocument
    {
        try {
            $marketplace = $organizer->marketplaceClient;

            if (!$marketplace) {
                Log::warning('OrganizerContractService: No marketplace client found for organizer', ['organizer_id' => $organizer->id]);
                return null;
            }

            // Idempotency: don't regenerate an existing contract.
            $existingContract = OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->where('document_type', 'organizer_contract')
                ->first();

            if ($existingContract && !$force) {
                return $existingContract;
            }

            // Resolve the organizer_contract template for this marketplace.
            $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                ->where('type', 'organizer_contract')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning('OrganizerContractService: No active organizer_contract template found', [
                    'organizer_id' => $organizer->id,
                    'marketplace_id' => $marketplace->id,
                ]);
                return null;
            }

            $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where('is_active', true)
                ->first();

            $variables = MarketplaceTaxTemplate::getVariablesForContext(
                $taxRegistry,
                $marketplace,
                $organizer,
                null, // No event for organizer contract
                null, // No order
                incrementContractNumber: true,
                template: $template,
            );

            $htmlContent = $template->processTemplate($variables);

            // Ensure proper UTF-8 encoding + a diacritics-capable font for DomPDF.
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

            $pdf = Pdf::loadHTML($htmlContent);
            $pdf->setPaper('A4', $template->page_orientation === 'landscape' ? 'landscape' : 'portrait');
            $pdfContent = $pdf->output();

            $fileName = sprintf('organizer_contract_%s_%s.pdf', $organizer->id, now()->format('YmdHis'));
            $filePath = sprintf('organizer-documents/%d/%s', $organizer->id, $fileName);

            Storage::disk('public')->put($filePath, $pdfContent);

            $document = OrganizerDocument::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer->id,
                'event_id' => null,
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
                    'contract_number' => $variables['marketplace_contract_number'] ?? null,
                    'variables' => $variables,
                    'generated_on_registration' => true,
                ],
                'issued_at' => now(),
            ]);

            // Auto-fill contract series/date on the organizer if not already set.
            $contractNumber = $variables['marketplace_contract_number'] ?? null;
            if ($contractNumber && !$organizer->contract_number_series) {
                $prefix = $marketplace->settings['contract_prefix'] ?? $marketplace->slug ?? 'CTR';
                $contractSeries = strtoupper($prefix) . '/' . $contractNumber;
                $organizer->updateQuietly([
                    'contract_number_series' => $contractSeries,
                    'contract_date' => now()->toDateString(),
                ]);
            }

            Log::info('OrganizerContractService: Organizer contract generated', [
                'organizer_id' => $organizer->id,
                'document_id' => $document->id,
                'document_path' => $filePath,
            ]);

            // Notify the marketplace admins about the generated document.
            try {
                $this->notificationService?->notifyDocumentGenerated(
                    $marketplace->id,
                    'organizer_contract',
                    $organizer->company_name ?? $organizer->name,
                    null,
                    route('filament.marketplace.resources.organizers.edit', ['record' => $organizer->id])
                );
            } catch (\Throwable $e) {
                Log::warning('OrganizerContractService: Failed to create document notification', ['error' => $e->getMessage()]);
            }

            return $document;
        } catch (\Throwable $e) {
            Log::error('OrganizerContractService: Failed to generate organizer contract', [
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
