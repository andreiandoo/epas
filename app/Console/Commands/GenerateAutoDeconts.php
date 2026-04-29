<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateAutoDeconts extends Command
{
    protected $signature = 'marketplace:generate-auto-deconts
                            {--dry-run : Show what would be created without actually creating}
                            {--days-after=3 : Number of days after event ends before generating decont}';

    protected $description = 'Auto-generate deconts for finished events with remaining available balance';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $daysAfter = (int) $this->option('days-after');

        $this->info('Scanning for finished events with available balance...');

        // Get all marketplace organizers
        $organizers = MarketplaceOrganizer::whereNotNull('verified_at')
            ->whereNotNull('marketplace_client_id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($organizers as $organizer) {
            $events = Event::where('marketplace_organizer_id', $organizer->id)
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->get();

            foreach ($events as $event) {
                // Check if event is past and ended at least $daysAfter days ago
                if (!$event->isPast()) {
                    continue;
                }

                $effectiveEnd = $event->getEffectiveEndDatetime();
                if ($effectiveEnd && $effectiveEnd->diffInDays(now()) < $daysAfter) {
                    continue;
                }

                // Check if an automated payout already exists for this event
                $existingAutoPayout = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
                    ->where('event_id', $event->id)
                    ->where('source', 'automated')
                    ->exists();

                if ($existingAutoPayout) {
                    continue;
                }

                // Calculate available balance
                $balance = $this->calculateEventBalance($event, $organizer);

                if ($balance <= 0) {
                    continue;
                }

                $title = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                    : ($event->title ?? 'Untitled');

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would create decont for: {$organizer->name} / {$title} - " . number_format($balance, 2) . " RON");
                    $created++;
                    continue;
                }

                try {
                    $this->createAutomatedPayout($organizer, $event, $balance);
                    $this->info("  Created decont: {$organizer->name} / {$title} - " . number_format($balance, 2) . " RON");
                    $created++;
                } catch (\Exception $e) {
                    $this->error("  Failed for {$organizer->name} / {$title}: " . $e->getMessage());
                    Log::error('GenerateAutoDeconts: Failed to create automated payout', [
                        'organizer_id' => $organizer->id,
                        'event_id' => $event->id,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }
        }

        $this->info("Done. Created: {$created}, Skipped/Failed: {$skipped}");

        return self::SUCCESS;
    }

    protected function calculateEventBalance(Event $event, MarketplaceOrganizer $organizer): float
    {
        $completedOrders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get();

        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        $grossRevenue = (float) $completedOrders->sum('total');
        $subtotalRevenue = (float) $completedOrders->sum('subtotal');

        if ($commissionMode === 'added_on_top') {
            $netRevenue = $subtotalRevenue;
            $commissionAmount = $grossRevenue - $subtotalRevenue;
        } else {
            $commissionAmount = round($grossRevenue * ($commissionRate / 100), 2);
            $netRevenue = $grossRevenue - $commissionAmount;
        }

        $eventPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->sum('amount');

        $eventPendingPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        return max(0, $netRevenue - $eventPayouts - $eventPendingPayouts);
    }

    protected function createAutomatedPayout(MarketplaceOrganizer $organizer, Event $event, float $netAmount): void
    {
        // Get bank account
        $bankAccount = $organizer->bankAccounts()->where('is_primary', true)->first()
            ?? $organizer->bankAccounts()->first();

        $payoutMethod = $bankAccount ? [
            'type' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'iban' => $bankAccount->iban,
            'account_holder' => $bankAccount->account_holder,
        ] : ($organizer->payout_details ?? []);

        // Calculate commission breakdown
        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        if ($commissionMode === 'added_on_top') {
            $grossAmount = $netAmount;
            $commissionAmount = 0;
        } else {
            $grossAmount = $netAmount / (1 - $commissionRate / 100);
            $commissionAmount = $grossAmount - $netAmount;
        }

        // Determine period
        $lastPayout = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->orderByDesc('period_end')
            ->first();

        $periodStart = $lastPayout
            ? $lastPayout->period_end->addDay()->toDateString()
            : $event->created_at->toDateString();

        $periodEnd = now()->toDateString();

        // Single source of truth for the per-ticket-type breakdown (same logic
        // as the event-edit "Vânzări" tab). Reads actual paid prices (not
        // catalog), allocates discounts/extras, derives commission_mode.
        $service = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $ticketBreakdown = $service->buildForPayout(
            $event,
            \Illuminate\Support\Carbon::parse($periodStart),
            \Illuminate\Support\Carbon::parse($periodEnd)
        );

        MarketplacePayout::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'amount' => round($netAmount, 2),
            'currency' => 'RON',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'gross_amount' => round($grossAmount, 2),
            'commission_amount' => round($commissionAmount, 2),
            'fees_amount' => 0,
            'adjustments_amount' => 0,
            'status' => 'pending',
            'source' => 'automated',
            'payout_method' => $payoutMethod,
            'admin_notes' => 'Decont generat automat după finalizarea evenimentului.',
            'ticket_breakdown' => !empty($ticketBreakdown) ? $ticketBreakdown : null,
            'commission_mode' => $commissionMode,
        ]);
    }
}
