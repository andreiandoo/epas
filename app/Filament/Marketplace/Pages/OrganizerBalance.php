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
            Actions\Action::make('create_payout')
                ->label('Înregistrează plată')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->organizer && $this->organizer->available_balance > 0)
                ->modalHeading('Înregistrează plată')
                ->modalDescription(fn () => 'Sold disponibil: ' . number_format($this->organizer->available_balance, 2, ',', '.') . ' RON')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Sumă (RON)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(fn () => (float) $this->organizer->available_balance)
                        ->default(fn () => (float) $this->organizer->available_balance)
                        ->suffix('RON')
                        ->helperText(fn () => 'Maximum: ' . number_format($this->organizer->available_balance, 2, ',', '.') . ' RON'),
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
                        ->placeholder('Numărul sau referința transferului')
                        ->helperText('Referința transferului bancar sau ID-ul tranzacției'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Note (opțional)')
                        ->rows(2)
                        ->placeholder('Detalii suplimentare despre plată'),
                ])
                ->action(function (array $data) {
                    $amount = (float) $data['amount'];

                    if ($amount > (float) $this->organizer->available_balance) {
                        Notification::make()
                            ->danger()
                            ->title('Sold insuficient')
                            ->body('Suma depășește soldul disponibil.')
                            ->send();
                        return;
                    }

                    $marketplace = static::getMarketplaceClient();

                    // Reserve the balance
                    $this->organizer->reserveBalanceForPayout($amount);

                    // Create payout record
                    $payout = MarketplacePayout::create([
                        'marketplace_client_id' => $marketplace->id,
                        'marketplace_organizer_id' => $this->organizer->id,
                        'amount' => $amount,
                        'currency' => 'RON',
                        'status' => 'processing',
                    ]);

                    // Complete the payout immediately (admin is recording a completed transfer)
                    $payout->complete($data['payment_reference'], $data['payment_notes'] ?? null);

                    // Refresh organizer data
                    $this->organizer->refresh();

                    Notification::make()
                        ->success()
                        ->title('Plată înregistrată')
                        ->body('Plata de ' . number_format($amount, 2, ',', '.') . " RON a fost înregistrată cu referința: {$data['payment_reference']}")
                        ->send();
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

                return $p
                    ? 'Sumă: ' . number_format((float) $p->amount, 2, ',', '.') . ' ' . ($p->currency ?? 'RON') . '. Organizatorul primește notificare că plata a fost efectuată.'
                    : null;
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
            ->orderByDesc('created_at')
            ->get();

        return [
            'organizer' => $this->organizer,
            'revenuePerEvent' => $revenuePerEvent,
            'payouts' => $payouts,
        ];
    }
}
