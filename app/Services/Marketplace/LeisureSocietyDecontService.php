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

        // Build the breakdown from the SAME source as the event "Vânzări" tab —
        // SalesBreakdownService reads the ACTUAL paid price per ticket
        // (tickets.price), which for Sf. Ana correctly reflects package-component
        // allocation (a 150-lei package splits its price across the ADULT/COPIL
        // component tickets, and AmBilet taxes those components). The shared
        // MarketplacePayout::buildRemainingTicketsItems pipeline instead falls
        // back to the ticket TYPE catalog price whenever the ticket meta has no
        // 'catalog' key (true for these package tickets), which inflates the
        // gross (31.580 vs the real 24.292). This path is LEISURE-ONLY — the
        // service is invoked exclusively for isLeisureVenue() events — so
        // ordinary/manual deconturi and every non-leisure organizer are
        // completely unaffected.
        $svc = app(SalesBreakdownService::class);
        $breakdown = $svc->build(
            $event,
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
            excludePos: true,
            dateColumn: 'paid_at',
        );

        // Keep only this society's ticket types. NULL / 'mix' / 'primary' all
        // resolve to primary — matching the Deconturi tab's by_issuer split.
        $belongsToIssuer = function (array $row) use ($issuer): bool {
            $tt = $row['tt'] ?? null;
            $bucket = (($tt?->issuing_company) === 'secondary') ? 'secondary' : 'primary';
            return $bucket === $issuer;
        };

        $ticketBreakdown = [];
        $finalGross = 0.0;
        $finalCommission = 0.0;
        $finalNet = 0.0;
        $discountAmount = 0.0;
        $modes = [];

        foreach (($breakdown['per_type'] ?? []) as $row) {
            if (!$belongsToIssuer($row)) {
                continue;
            }
            $qty = (int) ($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $gross = round((float) ($row['gross'] ?? 0), 2);
            $comm = round((float) ($row['commission_amount'] ?? 0), 2);
            $disc = round((float) ($row['discount'] ?? 0), 2);
            $net = round((float) ($row['net'] ?? 0), 2);
            $mode = (string) ($row['commission_mode'] ?? 'included');
            $modes[] = $mode;

            $finalGross += $gross;
            $finalCommission += $comm;
            $finalNet += $net;
            $discountAmount += $disc;

            // Canonical ticket_breakdown row shape (same keys the manual pipeline
            // persists) so the decont PDF + payout-view "Detalii bilete" render.
            $ticketBreakdown[] = [
                'ticket_type_id' => (int) ($row['ticket_type_id'] ?? 0),
                'ticket_type_name' => (string) ($row['ticket_type_name'] ?? ''),
                'qty' => $qty,
                'quantity' => $qty,
                'price' => (float) ($row['price'] ?? 0),
                'unit_price' => (float) ($row['price'] ?? 0),
                'gross' => $gross,
                'commission_per_ticket' => (float) ($row['commission_per_ticket'] ?? 0),
                'commission_amount' => $comm,
                'commission_mode' => $mode,
                'commission_type' => $row['commission_type'] ?? null,
                'commission_rate' => isset($row['commission_rate']) ? (float) $row['commission_rate'] : null,
                'commission_fixed' => isset($row['commission_fixed']) ? (float) $row['commission_fixed'] : null,
                'discount' => $disc,
                'extras' => round((float) ($row['extras'] ?? 0), 2),
                'net' => $net,
                'tiers' => [],
            ];
        }

        if (empty($ticketBreakdown)) {
            throw new \RuntimeException('Nu există bilete de decontat pentru această societate în perioada selectată.');
        }

        $finalGross = round($finalGross, 2);
        $finalCommission = round($finalCommission, 2);
        $finalNet = round($finalNet, 2);
        $discountAmount = round($discountAmount, 2);

        // Dominant commission mode (added_on_top wins ties — different downstream
        // effects), same rule as buildBreakdownFromSelection.
        $uniqueModes = array_values(array_unique($modes));
        if (count($uniqueModes) === 1) {
            $commissionMode = $uniqueModes[0];
        } elseif (in_array('added_on_top', $modes, true) || in_array('on_top', $modes, true)) {
            $commissionMode = 'added_on_top';
        } else {
            $commissionMode = $event->getEffectiveCommissionMode() ?: 'included';
        }

        // Decont amount: for commission-INCLUDED tickets the society is owed the
        // FULL gross it collected — the marketplace invoices its commission back
        // to the society separately (operator issues that invoice manually). For
        // added_on_top the commission was charged on top of the ticket price, so
        // the society's decont is the net (base) price.
        $decontAmount = $commissionMode === 'added_on_top' ? $finalNet : $finalGross;

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
            'amount' => $decontAmount,
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
