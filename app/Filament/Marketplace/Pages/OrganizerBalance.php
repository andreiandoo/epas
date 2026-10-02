<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Event;
use App\Services\Marketplace\SalesBreakdownService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            Actions\Action::make('refresh_figures')
                ->label('Reîmprospătează')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    Cache::forget($this->eventRowsCacheKey());
                    Notification::make()->success()->title('Cifre recalculate')->send();
                }),
            Actions\Action::make('record_advance')
                ->label('Înregistrează avans')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => (bool) $this->organizer && MarketplacePayout::advancesEnabled())
                ->modalHeading('Înregistrează avans')
                ->modalDescription(fn () => 'Plată în avans din soldul curent, înainte de decontul pe eveniment. Suma se scade imediat din sold; '
                    . 'deconturile aprobate ulterior se compensează automat din avans (cel mai vechi avans întâi), iar la plată rămâne de transferat doar diferența. '
                    . 'Sold disponibil: ' . number_format($this->liveAvailable(), 2, ',', '.') . ' RON.')
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
                            $over = (float) $state - $this->liveAvailable();

                            return $over > 0.004
                                ? 'Depășește soldul disponibil cu ' . number_format($over, 2, ',', '.') . ' RON'
                                : null;
                        })
                        ->hintColor('danger')
                        ->hintIcon(fn ($state) => (float) $state - $this->liveAvailable() > 0.004 ? 'heroicon-m-exclamation-triangle' : null)
                        ->helperText(fn () => 'Sold disponibil: ' . number_format($this->liveAvailable(), 2, ',', '.') . ' RON. Avansul poate depăși soldul — diferența rămâne de recuperat din vânzările următoare.'),
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
                    $availableBefore = $this->liveAvailable();
                    $marketplace = static::getMarketplaceClient();
                    $admin = Auth::guard('marketplace_admin')->user();

                    $payout = DB::transaction(function () use ($amount, $data, $marketplace, $admin) {
                        $paidDate = \Carbon\Carbon::parse($data['paid_at'])->toDateString();

                        $payout = MarketplacePayout::create([
                            'marketplace_client_id' => $marketplace->id,
                            'marketplace_organizer_id' => $this->organizer->id,
                            'event_id' => null,
                            'amount' => $amount,
                            'currency' => 'RON',
                            // Coloane obligatorii în tabel (NOT NULL, fără valoare
                            // implicită). Avansul nu acoperă o perioadă de vânzări
                            // și nu are brut / comision — perioada e ziua plății.
                            'period_start' => $paidDate,
                            'period_end' => $paidDate,
                            'gross_amount' => 0,
                            'commission_amount' => 0,
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

    /**
     * Cifrele pe eveniment — aceeași sursă ca /organizator/sold și
     * MarketplaceOrganizer::deriveBalances (SalesBreakdownService, cu regula
     * legacy_import). Serviciul e costisitor (un calcul complet pe fiecare
     * eveniment), iar Livewire re-randează pagina la fiecare acțiune, așa că
     * netul stă 5 minute în cache. Plățile NU sunt în cache: se citesc live,
     * ca „Finalizează" să se vadă imediat în carduri.
     */
    protected function eventRows(): array
    {
        return Cache::remember($this->eventRowsCacheKey(), 300, function () {
            $service = app(SalesBreakdownService::class);

            $events = Event::where('marketplace_organizer_id', $this->organizerId)
                ->where('marketplace_client_id', $this->organizer->marketplace_client_id)
                ->with('venue')
                ->get();

            $rows = [];
            foreach ($events as $event) {
                $settled = MarketplacePayout::eventHasLegacySettlement($event->id);
                $b = $service->build($event, excludeLegacyImport: ! $settled);

                $title = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? (collect($event->title)->first() ?: null))
                    : $event->title;
                $venueName = $event->venue
                    ? (is_array($event->venue->name) ? ($event->venue->name['ro'] ?? $event->venue->name['en'] ?? (collect($event->venue->name)->first() ?: null)) : $event->venue->name)
                    : null;
                $date = $event->event_date ?? $event->range_start_date ?? $event->starts_at;

                $rows[] = [
                    'id' => $event->id,
                    'title' => $title ?: ('Eveniment #' . $event->id),
                    'date' => $date?->format('d.m.Y'),
                    'sort_date' => $date?->format('Y-m-d') ?? '0000-00-00',
                    'venue' => $venueName,
                    'city' => $event->venue?->city,
                    'is_past' => $event->event_date ? $event->event_date->isPast() && ! $event->event_date->isToday() : false,
                    'revenue' => round((float) ($b['total_revenue'] ?? 0), 2),
                    'commission' => round((float) ($b['total_commission'] ?? 0), 2),
                    'discount' => round((float) ($b['total_discount'] ?? 0), 2),
                    'extras' => round((float) ($b['total_extras'] ?? 0), 2),
                    'net' => round((float) ($b['total_net'] ?? 0), 2),
                ];
            }

            return ['rows' => $rows, 'computed_at' => now()->format('H:i')];
        });
    }

    protected function eventRowsCacheKey(): string
    {
        return 'organizer_balance_page_events:' . $this->organizerId;
    }

    /**
     * Soldul organizatorului, calculat live — aceeași formulă ca
     * deriveBalances: disponibil = net − plătit − în procesare. Avansurile sunt
     * plăți finalizate; partea din deconturi acoperită din avans nu se
     * numără a doua oară.
     */
    protected function balanceSummary(): array
    {
        $eventData = $this->eventRows();
        $net = round((float) collect($eventData['rows'])->sum('net'), 2);

        $paid = round((float) MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)
            ->where('status', 'completed')->sum('amount')
            - MarketplacePayout::advanceOffsetForOrganizer($this->organizerId, ['completed']), 2);
        $pending = round((float) MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)
            ->whereIn('status', ['approved', 'processing'])->sum('amount')
            - MarketplacePayout::advanceOffsetForOrganizer($this->organizerId, ['approved', 'processing']), 2);

        return [
            'net' => $net,
            'paid' => $paid,
            'pending' => $pending,
            'available' => round($net - $paid - $pending, 2),
            'computed_at' => $eventData['computed_at'],
        ];
    }

    protected function liveAvailable(): float
    {
        return $this->balanceSummary()['available'];
    }

    public function getViewData(): array
    {
        $eventData = $this->eventRows();
        $summary = $this->balanceSummary();

        // Plăți pe eveniment, live: plătit (finalizate) și în procesare
        // (aprobate + în procesare). 'pending' = ciorne vechi, nu se numără.
        $paidByEvent = MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)
            ->whereNotNull('event_id')->where('status', 'completed')
            ->groupBy('event_id')->selectRaw('event_id, SUM(amount) as total')->pluck('total', 'event_id');
        $pendingByEvent = MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)
            ->whereNotNull('event_id')->whereIn('status', ['approved', 'processing'])
            ->groupBy('event_id')->selectRaw('event_id, SUM(amount) as total')->pluck('total', 'event_id');

        $eventRows = collect($eventData['rows'])->map(function (array $row) use ($paidByEvent, $pendingByEvent) {
            $row['paid'] = round((float) ($paidByEvent[$row['id']] ?? 0), 2);
            $row['pending'] = round((float) ($pendingByEvent[$row['id']] ?? 0), 2);
            $row['balance'] = round($row['net'] - $row['paid'] - $row['pending'], 2);

            return $row;
        })
            // Evenimentele fără vânzări și fără deconturi nu spun nimic aici.
            ->filter(fn ($r) => abs($r['net']) > 0.004 || $r['paid'] > 0 || $r['pending'] > 0 || abs($r['revenue']) > 0.004)
            ->sortByDesc('sort_date')
            ->values();

        // Plăți fără eveniment: avansuri și deconturi multi-eveniment. Intră
        // în sold (carduri), dar nu aparțin niciunui rând din tabel.
        $orgWide = MarketplacePayout::where('marketplace_organizer_id', $this->organizerId)
            ->whereNull('event_id')
            ->whereIn('status', ['approved', 'processing', 'completed'])
            ->get(['id', 'amount', 'status', 'source']);

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
            'summary' => $summary,
            'eventRows' => $eventRows,
            'orgWidePaid' => round((float) $orgWide->where('status', 'completed')->sum('amount'), 2),
            'orgWidePending' => round((float) $orgWide->whereIn('status', ['approved', 'processing'])->sum('amount'), 2),
            'advanceOffset' => round($eventRows->sum('paid') + $eventRows->sum('pending')
                + (float) $orgWide->sum('amount') - $summary['paid'] - $summary['pending'], 2),
            'payouts' => $payouts,
            'advances' => $advances,
            'advanceOpen' => round((float) $advances->sum('advance_remaining'), 2),
        ];
    }
}
