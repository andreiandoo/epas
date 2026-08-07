<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTaxRegistry;
use App\Models\MarketplaceTaxTemplate;
use App\Models\OrganizerDocument;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a settlement (decont) for ONE issuing company over a period,
 * straight from the leisure event "Deconturi" tab buttons.
 *
 * Deliberately reuses the EXACT money pipeline the manual "Crează Decont Manual"
 * modal uses — MarketplacePayout::buildRemainingTicketsItems (period-scoped via
 * a cutoff) + MarketplacePayout::buildBreakdownFromSelection — filtered down to
 * the society's ticket types. So the amounts are correct-by-construction: this
 * is exactly what the operator would get by opening the manual modal and
 * selecting only that society's tickets. No new financial math is introduced.
 *
 * Refunds are NOT auto-subtracted (mirrors calculateEventFinancials + the reason
 * the auto-generate path was removed on 2026-07-28): the operator links refunds
 * afterwards on the payout detail page if needed.
 */
class LeisureSocietyDecontService
{
    /**
     * @throws \RuntimeException on guard failures (no organizer / duplicate / empty)
     */
    public function generate(
        Event $event,
        string $issuer,
        Carbon $from,
        Carbon $to,
        int $marketplaceClientId,
        ?int $approvedById = null
    ): MarketplacePayout {
        $issuer = $issuer === 'secondary' ? 'secondary' : 'primary';

        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) {
            throw new \RuntimeException('Evenimentul nu are organizator asociat.');
        }
        if ($issuer === 'secondary' && !$organizer->has_secondary_issuer) {
            throw new \RuntimeException('Organizatorul nu are o a doua societate emitentă configurată.');
        }

        // Idempotency: at most one active decont per (event, issuer, period start).
        $existing = MarketplacePayout::where('event_id', $event->id)
            ->where('issuing_company', $issuer)
            ->whereDate('period_start', $from->toDateString())
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->exists();
        if ($existing) {
            throw new \RuntimeException('Există deja un decont pentru această societate și perioadă.');
        }

        // Period-scoped remaining tickets (same source as the manual modal),
        // then keep only this society's ticket types. NULL / 'mix' / 'primary'
        // all resolve to primary — matching the Deconturi tab's by_issuer split.
        $cutoff = $to->copy()->endOfDay();
        $items = MarketplacePayout::buildRemainingTicketsItems($event, null, $cutoff);

        $ttIds = array_values(array_filter(array_map(
            fn ($i) => (int) ($i['ticket_type_id'] ?? 0),
            $items
        )));
        $issuerMap = empty($ttIds)
            ? []
            : TicketType::whereIn('id', $ttIds)->pluck('issuing_company', 'id')->toArray();
        $resolve = fn (int $ttId): string => (($issuerMap[$ttId] ?? null) === 'secondary') ? 'secondary' : 'primary';

        $filtered = array_values(array_filter(
            $items,
            fn ($i) => $resolve((int) ($i['ticket_type_id'] ?? 0)) === $issuer
        ));

        if (empty($filtered)) {
            throw new \RuntimeException('Nu există bilete de decontat pentru această societate în perioada selectată.');
        }

        // Same breakdown builder the manual submit uses (targetNet = null → no
        // rescaling; the filtered selection IS the source of truth).
        $built = MarketplacePayout::buildBreakdownFromSelection($filtered, $event, null);
        $ticketBreakdown = $built['rows'];
        $finalGross = round((float) ($built['totals']['gross'] ?? 0), 2);
        $finalCommission = round((float) ($built['totals']['commission'] ?? 0), 2);
        $finalNet = round((float) ($built['totals']['net'] ?? 0), 2);
        $discountAmount = (float) ($built['totals']['discount'] ?? 0);
        $commissionMode = $built['commission_mode'] ?? ($event->getEffectiveCommissionMode() ?: 'included');

        // Bank account for THIS society (source of truth), then fall back to the
        // organizer's primary account so we never persist a null IBAN.
        $bank = MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizer->id)
                ->where('issuing_company', $issuer)
                ->orderByDesc('is_primary')->orderByDesc('id')->first()
            ?? MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizer->id)
                ->where('is_primary', true)->first()
            ?? MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizer->id)->first();

        $payoutMethod = $bank ? [
            'type' => 'bank_transfer',
            'bank_account_id' => $bank->id,
            'bank_name' => $bank->bank_name,
            'iban' => $bank->iban,
            'account_holder' => $bank->account_holder,
        ] : null;

        $payout = MarketplacePayout::create([
            'marketplace_client_id' => $marketplaceClientId,
            'marketplace_organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'issuing_company' => $issuer,
            'amount' => $finalNet,
            'currency' => 'RON',
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'gross_amount' => $finalGross,
            'commission_amount' => $finalCommission,
            'discount_amount' => $discountAmount,
            'refund_amount' => 0,
            'fees_amount' => 0,
            'adjustments_amount' => 0,
            'status' => 'approved',
            'source' => 'manual',
            'approved_by' => $approvedById,
            'approved_at' => now(),
            'payout_method' => $payoutMethod,
            'ticket_breakdown' => !empty($ticketBreakdown) ? $ticketBreakdown : null,
            'commission_mode' => $commissionMode,
            'invoice_recipient_type' => $commissionMode === 'added_on_top' ? 'general_client' : 'organizer',
            // Empty → boot hook's assignDecontSeries() auto-generates.
            'decont_series' => null,
        ]);

        $this->generateDecontDocument($payout, $event, $organizer, $marketplaceClientId, $commissionMode);

        return $payout;
    }

    /**
     * Render + persist the decont PDF. Mirrors the manual create action's
     * document block. getVariablesForContext() swaps in the secondary society's
     * legal identity + IBAN when the payout is tagged issuing_company='secondary'.
     */
    protected function generateDecontDocument(
        MarketplacePayout $payout,
        Event $event,
        MarketplaceOrganizer $organizer,
        int $marketplaceClientId,
        string $commissionMode
    ): void {
        try {
            $templateType = $commissionMode === 'added_on_top' ? 'decont_ontop' : 'decont_inclus';

            $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplaceClientId)
                    ->where('type', $templateType)->where('is_active', true)->first()
                ?? MarketplaceTaxTemplate::where('marketplace_client_id', $marketplaceClientId)
                    ->where('type', 'decont')->where('is_active', true)->first();

            if (!$template) {
                return;
            }

            $marketplace = MarketplaceClient::find($marketplaceClientId);
            $taxRegistry = MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                ->where(function ($q) use ($event) {
                    $venue = $event->venue;
                    if ($venue?->county) {
                        $q->where('county', $venue->county);
                    }
                    if ($venue?->city) {
                        $q->orWhere('city', $venue->city);
                    }
                })->first();

            $variables = $template->getVariablesForContext(
                taxRegistry: $taxRegistry,
                marketplace: $marketplace,
                organizer: $organizer,
                event: $event,
                payout: $payout,
                template: $template,
            );

            $htmlContent = $template->processTemplate($variables);
            if (!str_contains($htmlContent, '<html')) {
                $htmlContent = '<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}</style></head><body>' . $htmlContent . '</body></html>';
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);
            $pdf->setPaper('A4', $template->page_orientation ?? 'portrait');
            $pdfContent = $pdf->output();

            $fileName = 'decont_' . $payout->reference . '_' . now()->format('Ymd_His') . '.pdf';
            $filePath = "organizer-documents/{$organizer->id}/{$fileName}";
            Storage::disk('public')->put($filePath, $pdfContent);

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
                    'commission_mode' => $commissionMode,
                    'issuing_company' => $payout->issuing_company,
                    'template_name' => $template->name,
                ],
                'issued_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Leisure society decont document generation failed: ' . $e->getMessage());
        }
    }
}
