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

    /**
     * Apply the organizer's drawn e-signature (SES) to their contract and
     * produce the FINAL signed PDF, keeping the same contract number/identity as
     * the unsigned original (regenerating via generate(force:true) would consume
     * a fresh number and spawn a duplicate document).
     *
     * @param  string  $signatureDataUri  data:image/png;base64,... from the pad
     * @param  array{ip?:string,user_agent?:string,agreement?:string}  $meta
     * @return OrganizerDocument|null  the signed document, or null on failure
     */
    public function signContract(MarketplaceOrganizer $organizer, string $signatureDataUri, array $meta = []): ?OrganizerDocument
    {
        try {
            $marketplace = $organizer->marketplaceClient;
            if (!$marketplace) {
                return null;
            }

            // 1. Decode + sanity-check the signature image.
            if (!preg_match('#^data:image/(png|jpe?g);base64,#i', $signatureDataUri)) {
                Log::warning('signContract: invalid signature data URI', ['organizer_id' => $organizer->id]);
                return null;
            }
            $binary = base64_decode(substr($signatureDataUri, strpos($signatureDataUri, ',') + 1), true);
            if ($binary === false || strlen($binary) < 100 || strlen($binary) > 2_000_000) {
                Log::warning('signContract: bad signature payload', ['organizer_id' => $organizer->id]);
                return null;
            }

            // 2. Store the signature (public disk) so DomPDF can embed it.
            $sigPath = sprintf('organizer-signatures/%d/signature_%s.png', $organizer->id, now()->format('YmdHis'));
            Storage::disk('public')->put($sigPath, $binary);

            // 3. Persist signature + audit trail on the organizer. saveQuietly so
            //    the observer (which reacts to verified_at / test_pos_enabled) is
            //    not touched by a signature-only change.
            $organizer->forceFill([
                'signature_image' => $sigPath,
                'contract_signed_at' => now(),
                'contract_signed_ip' => $meta['ip'] ?? null,
                'contract_signed_user_agent' => $meta['user_agent'] ?? null,
            ])->saveQuietly();

            // 4. Resolve template + the existing (unsigned) contract to re-render
            //    in place — same number, now with the signature embedded.
            $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace->id)
                ->where('type', 'organizer_contract')
                ->where('is_active', true)
                ->first();
            if (!$template) {
                Log::warning('signContract: no active organizer_contract template', ['organizer_id' => $organizer->id]);
                return null;
            }

            $existing = OrganizerDocument::where('marketplace_organizer_id', $organizer->id)
                ->where('document_type', 'organizer_contract')
                ->latest('id')
                ->first();

            $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where('is_active', true)
                ->first();

            // Re-render WITHOUT consuming a new contract number, then pin the
            // number to the one this contract was first issued under.
            $variables = MarketplaceTaxTemplate::getVariablesForContext(
                $taxRegistry, $marketplace, $organizer, null, null,
                incrementContractNumber: false, template: $template,
            );
            $existingNumber = $existing?->document_data['contract_number'] ?? ($variables['marketplace_contract_number'] ?? null);
            if ($existingNumber !== null) {
                $variables['marketplace_contract_number'] = $existingNumber;
            }

            $html = $this->wrapContractHtml($template->processTemplate($variables));
            $pdfContent = $this->renderContractPdf($html, $template);

            $fileName = sprintf('organizer_contract_signed_%s_%s.pdf', $organizer->id, now()->format('YmdHis'));
            $filePath = sprintf('organizer-documents/%d/%s', $organizer->id, $fileName);
            Storage::disk('public')->put($filePath, $pdfContent);

            $documentData = array_merge($existing?->document_data ?? [], [
                'contract_number' => $existingNumber,
                'signed' => true,
                'signed_at' => now()->toIso8601String(),
                'signed_ip' => $meta['ip'] ?? null,
                'signed_user_agent' => $meta['user_agent'] ?? null,
                'agreement_text' => $meta['agreement'] ?? null,
                'signature_path' => $sigPath,
                'signature_sha256' => hash('sha256', $binary),
                'pdf_sha256' => hash('sha256', $pdfContent),
                'variables' => $variables,
            ]);

            if ($existing) {
                $existing->update([
                    'tax_template_id' => $template->id,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_size' => strlen($pdfContent),
                    'html_content' => $html,
                    'document_data' => $documentData,
                    'issued_at' => $existing->issued_at ?? now(),
                ]);
                $document = $existing;
            } else {
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
                    'html_content' => $html,
                    'document_data' => $documentData,
                    'issued_at' => now(),
                ]);
            }

            Log::info('signContract: organizer contract signed', [
                'organizer_id' => $organizer->id,
                'document_id' => $document->id,
            ]);

            return $document;
        } catch (\Throwable $e) {
            Log::error('signContract: failed to sign organizer contract', [
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Wrap raw template HTML with a UTF-8 header + a diacritics-capable font so
     * DomPDF renders Romanian text correctly. Mirrors the inline logic in
     * generate().
     */
    protected function wrapContractHtml(string $htmlContent): string
    {
        if (stripos($htmlContent, '<html') === false) {
            return '<!DOCTYPE html>
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
        }
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

        return $htmlContent;
    }

    protected function renderContractPdf(string $wrappedHtml, MarketplaceTaxTemplate $template): string
    {
        $pdf = Pdf::loadHTML($wrappedHtml);
        $pdf->setPaper('A4', $template->page_orientation === 'landscape' ? 'landscape' : 'portrait');

        return $pdf->output();
    }
}
