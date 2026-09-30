<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizerBalance extends Page
{
    use HasMarketplaceContext;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wallet';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'organizers/{id}/balance';
    protected string $view = 'filament.marketplace.pages.organizer-balance';

    public ?int $organizerId = null;
    public ?MarketplaceOrganizer $organizer = null;

    public function mount(int $id): void
    {
        $marketplace = static::getMarketplaceClient();

        $this->organizer = MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
            ->findOrFail($id);
        $this->organizerId = $id;
    }

    public function getTitle(): string
    {
        return 'Sold: ' . ($this->organizer?->name ?? 'Organizator');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/marketplace/balances') => 'Solduri',
            '#' => $this->organizer?->name ?? 'Organizator',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Înapoi la solduri')
                ->url(url('/marketplace/balances'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
            Actions\Action::make('record_advance')
                ->label('Înregistrează avans')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => (bool) $this->organizer && MarketplacePayout::advancesEnabled())
                ->modalHeading('Înregistrează avans')
                ->modalDescription(fn () => 'Plată în avans din soldul curent, înainte de decontul pe eveniment. Suma se scade imediat din sold; '
                    . 'deconturile aprobate ulterior se compensează automat din avans (cel mai vechi avans întâi), iar la plată rămâne de transferat doar diferența. '
                    . 'Sold disponibil: ' . number_format((float) $this->organizer->available_balance, 2, ',', '.') . ' RON.')
                ->modalSubmitActionLabel('Înregistrează avansul')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Sumă avans (RON)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->suffix('RON')
                        ->live(onBlur: true)
                        ->hint(function ($state) {
                            $over = (float) $state - (float) $this->organizer->available_balance;

                            return $over > 0.004
                                ? 'Depășește soldul disponibil cu ' . number_format($over, 2, ',', '.') . ' RON'
                                : null;
                        })
                        ->hintColor('danger')
                        ->hintIcon(fn ($state) => (float) $state - (float) $this->organizer->available_balance > 0.004 ? 'heroicon-m-exclamation-triangle' : null)
                        ->helperText(fn () => 'Sold disponibil: ' . number_format((float) $this->organizer->available_balance, 2, ',', '.') . ' RON. Avansul poate depăși soldul — diferența rămâne de recuperat din vânzările următoare.'),
                    Forms\Components\DatePicker::make('paid_at')
                        ->label('Data plății')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                    Forms\Components\Placeholder::make('bank_info')
                        ->label('Date bancare')
                        ->content(function () {
                            // Prefer the primary row from marketplace_organizer_bank_accounts
                            // (source of truth since 2026-02) over the legacy
                            // organizer.bank_name / .iban columns that stop being
                            // written once an organizer touches the new
                            // bank-accounts UI. Falls back to the legacy columns
                            // for un-migrated organizers.
                            $primary = $this->organizer
                                ? \DB::table('marketplace_organizer_bank_accounts')
                                    ->where('marketplace_organizer_id', $this->organizer->id)
                                    ->orderByDesc('is_primary')
                                    ->orderByDesc('id')
                                    ->first()
                                : null;
                            $bankName = $primary->bank_name ?? $this->organizer->bank_name ?? '';
                            $iban = $primary->iban ?? $this->organizer->iban ?? '';
                            return new \Illuminate\Support\HtmlString(
                                '<div class="space-y-1">' .
                                '<div><span class="font-medium text-gray-500 dark:text-gray-400">Bancă:</span> <span class="text-gray-900 dark:text-white">' . e($bankName ?: 'Necompletat') . '</span></div>' .
                                '<div><span class="font-medium text-gray-500 dark:text-gray-400">IBAN:</span> <span class="font-mono text-gray-900 dark:text-white">' . e($iban ?: 'Necompletat') . '</span></div>' .
                                '</div>'
                            );
                        }),
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Referință plată')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Numărul sau referința transferului')
                        ->helperText('Referința transferului bancar sau ID-ul tranzacției'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Note (opțional)')
                        ->rows(2)
                        ->placeholder('Detalii suplimentare despre avans'),
                ])
                ->action(function (array $data) {
                    $amount = round((float) $data['amount'], 2);
                    $availableBefore = (float) $this->organizer->available_balance;
                    $marketplace = static::getMarketplaceClient();
                    $admin = Auth::guard('marketplace_admin')->user();

                    $payout = DB::transaction(function () use ($amount, $data, $marketplace, $admin) {
                        $payout = MarketplacePayout::create([
                            'marketplace_client_id' => $marketplace->id,
                            'marketplace_organizer_id' => $this->organizer->id,
                            'event_id' => null,
                            'amount' => $amount,
                            'currency' => 'RON',
                            'status' => 'processing',
                            'source' => MarketplacePayout::SOURCE_ADVANCE,
                            'processed_by' => $admin?->id,
                            'processed_at' => now(),
                            'admin_notes' => 'Avans din sold curent',
                        ]);

                        // Același traseu ca orice plată: rezervă, apoi finalizează
                        // (sold, total plătit, tranzacție, notificare organizator).
                        $this->organizer->reserveBalanceForPayout($amount);
                        $payout->complete($data['payment_reference'], $data['payment_notes'] ?? null);

                        // Data reală a transferului, dacă nu e azi.
                        $paidAt = \Carbon\Carbon::parse($data['paid_at']);
                        if (! $paidAt->isToday()) {
                            $payout->completed_at = $paidAt->setTime(12, 0);
                            $payout->saveQuietly();
                        }

                        return $payout;
                    });

                    $this->organizer->refresh();

                    $notification = Notification::make()
                        ->title('Avans înregistrat')
                        ->body('Avansul ' . $payout->reference . ' de ' . number_format($amount, 2, ',', '.') . ' RON a fost înregistrat și scăzut din sold.');

                    if ($amount - $availableBefore > 0.004) {
                        $notification->warning()->body(
                            'Avansul ' . $payout->reference . ' de ' . number_format($amount, 2, ',', '.') . ' RON a fost înregistrat, dar depășește soldul disponibil cu '
                            . number_format($amount - $availableBefore, 2, ',', '.') . ' RON. Diferența se recuperează din vânzările următoare.'
                        )->persistent();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }

    /**
     * "Finalizează" pe un rând din Lista deconturi — același flux ca pe
     * pagina decontului (MarketplacePayout::complete: status, sold,
     * tranzacție, notificare). Doar pentru deconturi aprobate / în procesare.
     */
    public function completePayoutAction(): Actions\Action
    {
        return Actions\Action::make('completePayout')
            ->label('Finalizează')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->size('sm')
            ->modalHeading(fn (array $arguments) => 'Finalizează decontul ' . ($this->findPayout($arguments['payout'] ?? null)?->reference ?? ''))
            ->modalDescription(function (array $arguments) {
                $p = $this->findPayout($arguments['payout'] ?? null);

                if (! $p) {
                    return null;
                }
                $cur = ' ' . ($p->currency ?? 'RON');
                $covered = $p->advanceCoveredAmount();
                $text = 'Sumă decont: ' . number_format((float) $p->amount, 2, ',', '.') . $cur . '.';
                if ($covered > 0) {
                    $text .= ' Compensat din avans: ' . number_format($covered, 2, ',', '.') . $cur
                        . '. Rest de plată: ' . number_format($p->cashAmount(), 2, ',', '.') . $cur . '.';
                }

                return $text . ' Organizatorul primește notificare că plata a fost efectuată.';
            })
            ->fillForm(function (array $arguments) {
                $p = $this->findPayout($arguments['payout'] ?? null);

                // Decont acoperit integral din avans: nu există transfer bancar.
                return ($p && $p->advanceCoveredAmount() > 0 && $p->cashAmount() <= 0.004)
                    ? ['payment_reference' => 'Compensat integral din avans']
                    : [];
            })
            ->modalSubmitActionLabel('Marchează finalizat')
            ->form([
                Forms\Components\TextInput::make('payment_reference')
                    ->label('Referință plată')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Referința transferului bancar sau ID-ul tranzacției'),
                Forms\Components\Textarea::make('payment_notes')
                    ->label('Note (opțional)')
                    ->rows(2),
            ])
            ->action(function (array $data, array $arguments) {
                $payout = $this->findPayout($arguments['payout'] ?? null);
                if (! $payout || ! $payout->canBeCompleted()) {
                    Notification::make()->danger()->title('Decontul nu poate fi finalizat')
                        ->body('Doar deconturile aprobate sau în procesare pot fi marcate finalizate.')->send();

                    return;
                }

                $payout->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                $this->organizer->refresh();

                Notification::make()->success()->title('Decont finalizat')
                    ->body("Decontul {$payout->reference} a fost marcat finalizat (ref: {$data['payment_reference']}).")->send();
            });
    }

    /**
     * Adaugă / corectează referința plății pe un decont deja finalizat.
     * Nu atinge soldul — doar payment_reference + payment_notes.
     */
    public function editPaymentReferenceAction(): Actions\Action
    {
        return Actions\Action::make('editPaymentReference')
            ->label(fn (array $arguments) => $this->findPayout($arguments['payout'] ?? null)?->payment_reference ? 'Modifică referința' : 'Adaugă referință')
            ->icon('heroicon-m-pencil-square')
            ->color('gray')
            ->size('sm')
            ->link()
            ->modalHeading(fn (array $arguments) => 'Referință plată — ' . ($this->findPayout($arguments['payout'] ?? null)?->reference ?? ''))
            ->modalSubmitActionLabel('Salvează')
            ->fillForm(function (array $arguments) {
                $p = $this->findPayout($arguments['payout'] ?? null);

                return ['payment_reference' => $p?->payment_reference, 'payment_notes' => $p?->payment_notes];
            })
            ->form([
                Forms\Components\TextInput::make('payment_reference')
                    ->label('Referință plată')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('payment_notes')
                    ->label('Note (opțional)')
                    ->rows(2),
            ])
            ->action(function (array $data, array $arguments) {
                $payout = $this->findPayout($arguments['payout'] ?? null);
                if (! $payout || ! $payout->isCompleted()) {
                    return;
                }

                $payout->update([
                    'payment_reference' => $data['payment_reference'],
                    'payment_notes' => $data['payment_notes'] ?? null,
                ]);

                Notification::make()->success()->title('Referință salvată')->send();
            });
    }

    /** Decont al acestui organizator — niciodată al altuia, chiar dacă argumentele sunt modificate. */
    protected function findPayout($id): ?MarketplacePayout
    {
        if (! $id) {
            return null;
        }

        return MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)->find((int) $id);
    }

    public function getViewData(): array
    {
        // Revenue per event
        $revenuePerEvent = Order::query()
            ->where('marketplace_organizer_id', $this->organizerId)
            ->where('status', 'completed')
            // Comenzile de test (Test POS) nu intra in venituri
            ->whereNotIn('source', ['external_import', 'test_order', 'pos_test'])
            ->select(
                'marketplace_event_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(subtotal) as gross_revenue'),
                DB::raw('SUM(commission_amount) as total_commission'),
                DB::raw('SUM(subtotal) - SUM(commission_amount) as net_revenue')
            )
            ->groupBy('marketplace_event_id')
            ->with('marketplaceEvent')
            ->get();

        // Payout history — eager-load event + venue for the Eveniment column.
        $payouts = MarketplacePayout::query()
            ->where('marketplace_organizer_id', $this->organizerId)
            ->with(['event.venue'])
            ->when(MarketplacePayout::advancesEnabled(), fn ($q) => $q->withSum('advanceAllocations as advance_covered', 'amount'))
            ->orderByDesc('created_at')
            ->get();

        // Avansuri, cele mai vechi primele, cu ce deconturi au acoperit.
        $advances = MarketplacePayout::advancesForOrganizer($this->organizerId);

        return [
            'organizer' => $this->organizer,
            'revenuePerEvent' => $revenuePerEvent,
            'payouts' => $payouts,
            'advances' => $advances,
            'advanceOpen' => round((float) $advances->sum('advance_remaining'), 2),
        ];
    }
}
