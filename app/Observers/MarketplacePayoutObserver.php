<?php

namespace App\Observers;

use App\Models\MarketplacePayout;
use App\Models\MarketplaceTaxTemplate;
use App\Models\MarketplaceTaxRegistry;
use App\Models\OrganizerDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MarketplacePayoutObserver
{
    /**
     * Handle the MarketplacePayout "updated" event.
     */
    public function updated(MarketplacePayout $payout): void
    {
        // Generate decont when status changes to completed
        if ($payout->isDirty('status') && in_array($payout->status, ['approved', 'completed'])) {
            $this->generateDecont($payout);
        }
    }

    /**
     * Generate decont document for completed payout
     */
    protected function generateDecont(MarketplacePayout $payout): void
    {
        try {
            $marketplace = $payout->marketplaceClient;
            $organizer = $payout->organizer;

            if (!$marketplace || !$organizer) {
                Log::warning('MarketplacePayoutObserver: Missing marketplace or organizer', [
                    'payout_id' => $payout->id,
                ]);
                return;
            }

            // Check if decont already exists for this payout
            $existingDecont = OrganizerDocument::where('marketplace_payout_id', $payout->id)
                ->where('document_type', 'decont')
                ->first();

            if ($existingDecont) {
                Log::info('MarketplacePayoutObserver: Decont already exists for payout', [
                    'payout_id' => $payout->id,
                ]);
                return;
            }

            // Get active decont template
            $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                ->where('type', 'decont')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning('MarketplacePayoutObserver: No active decont template found', [
                    'payout_id' => $payout->id,
                    'marketplace_id' => $marketplace->id,
                ]);
                return;
            }

            // Get tax registry: prefer event-specific, fall back to default
            $taxRegistry = null;
            if ($payout->event && $payout->event->marketplace_tax_registry_id) {
                $taxRegistry = MarketplaceTaxRegistry::find($payout->event->marketplace_tax_registry_id);
            }
            if (!$taxRegistry) {
                $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                    ->where('is_active', true)
                    ->first();
            }

            // Build variables with payout context
            $variables = MarketplaceTaxTemplate::getVariablesForContext(
                $taxRegistry,
                $marketplace,
                $organizer,
                $payout->event, // May be null for general payouts
                null, // No order
                incrementContractNumber: false,
                payout: $payout
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

            // Handle page 2 if template has it
            $htmlContentPage2 = null;
            if ($template->html_content_page_2) {
                $htmlContentPage2 = $template->processTemplate($variables);
            }

            // Generate PDF
            $pdf = Pdf::loadHTML($htmlContent);

            if ($template->page_orientation === 'landscape') {
                $pdf->setPaper('A4', 'landscape');
            } else {
                $pdf->setPaper('A4', 'portrait');
            }

            $pdfContent = $pdf->output();

            // Generate filename
            $fileName = sprintf(
                'decont_%s_%s.pdf',
                $payout->reference,
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
                'event_id' => $payout->event_id,
                'marketplace_payout_id' => $payout->id,
                'tax_template_id' => $template->id,
                'title' => 'Decont ' . $payout->reference,
                'document_type' => 'decont',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => strlen($pdfContent),
                'html_content' => $htmlContent,
                'document_data' => [
                    'payout_reference' => $payout->reference,
                    'payout_amount' => $payout->amount,
                    'payout_currency' => $payout->currency,
                    'organizer_name' => $organizer->company_name ?? $organizer->name,
                    'template_name' => $template->name,
                    'variables' => $variables,
                ],
                'issued_at' => now(),
            ]);

            Log::info('MarketplacePayoutObserver: Decont generated successfully', [
                'payout_id' => $payout->id,
                'document_path' => $filePath,
            ]);

        } catch (\Exception $e) {
            Log::error('MarketplacePayoutObserver: Failed to generate decont', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
