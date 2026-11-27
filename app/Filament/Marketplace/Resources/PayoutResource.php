<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\PayoutResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceAdmin;
use App\Support\MarketplaceTz;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Support\Enums\IconPosition;

class PayoutResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    protected static ?string $model = MarketplacePayout::class;
    protected static ?string $navigationLabel = 'Deconturi';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('Payout Request'))
                    ->schema([
                        Forms\Components\Placeholder::make('reference_display')
                            ->label(__('Reference'))
                            ->content(fn (?MarketplacePayout $record): string => $record?->reference ?? '-'),

                        Forms\Components\Placeholder::make('organizer_display')
                            ->label(__('Organizer'))
                            ->content(fn (?MarketplacePayout $record): string => $record?->organizer?->name ?? '-'),

                        Forms\Components\Placeholder::make('event_display')
                            ->label(__('Event'))
                            ->content(function (?MarketplacePayout $record): string {
                                if (!$record?->event) return 'General payout (no specific event)';
                                $title = $record->event->title;
                                return is_array($title)
                                    ? ($title['ro'] ?? $title['en'] ?? reset($title) ?? 'Untitled')
                                    : ($title ?? 'Untitled');
                            }),

                        Forms\Components\Placeholder::make('amount_display')
                            ->label(__('Amount'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('status_display')
                            ->label(__('Status'))
                            ->content(fn (?MarketplacePayout $record): string => $record?->status_label ?? '-'),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label(__('Requested At'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                MarketplaceTz::fmt($record?->created_at, 'd.m.Y H:i', $record?->marketplaceClient, fallback: '-')),
                    ])
                    ->columns(3),

                Section::make(__('Amount Breakdown'))
                    ->schema([
                        Forms\Components\Placeholder::make('gross_amount_display')
                            ->label(__('Gross Amount'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->gross_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('commission_amount_display')
                            ->label(__('Commission'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->commission_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('fees_amount_display')
                            ->label(__('Fees'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? '-' . number_format($record->fees_amount, 2) . ' ' . $record->currency : '-'),

                        Forms\Components\Placeholder::make('net_amount_display')
                            ->label(__('Net Amount'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record ? number_format($record->amount, 2) . ' ' . $record->currency : '-'),
                    ])
                    ->columns(4),

                Section::make(__('Payout Method'))
                    ->schema([
                        Forms\Components\Placeholder::make('bank_name_display')
                            ->label(__('Bank'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['bank_name'] ?? '-'),

                        Forms\Components\Placeholder::make('iban_display')
                            ->label(__('IBAN'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['iban'] ?? '-'),

                        Forms\Components\Placeholder::make('account_holder_display')
                            ->label(__('Account Holder'))
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->payout_method['account_holder'] ?? '-'),
                    ])
                    ->columns(3),

                Section::make(__('Organizer Notes'))
                    ->schema([
                        Forms\Components\Placeholder::make('organizer_notes_display')
                            ->label('')
                            ->content(fn (?MarketplacePayout $record): string =>
                                $record?->organizer_notes ?? 'No notes from organizer')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn (?MarketplacePayout $record): bool => empty($record?->organizer_notes)),

                Section::make(__('Admin Notes'))
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ])->columns(1);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->components([
                // ========== OPERATOR STEPS (wizard) ==========
                // Shows the remaining actions in order; each disappears once
                // done. Hidden when nothing is left (or payout dead).
                \Filament\Schemas\Components\Group::make([
                    Infolists\Components\ViewEntry::make('operator_steps')
                        ->hiddenLabel()
                        ->view('filament.infolists.payout-operator-steps')
                        ->columnSpanFull(),
                ])
                    ->columnSpanFull()
                    ->visible(fn ($record) => collect($record->getOperatorSteps())->where('done', false)->isNotEmpty()),

                // Decont respins: motivul sus, cât se vede.
                \Filament\Schemas\Components\Group::make([
                    Infolists\Components\TextEntry::make('rejection_view')
                        ->hiddenLabel()
                        ->getStateUsing(fn () => '—')
                        ->formatStateUsing(fn ($state, $record) => static::renderRejection($record))
                        ->html()
                        ->columnSpanFull(),
                ])
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->status === 'rejected'),

                // ========== MAIN INFO ==========
                \Filament\Schemas\Components\Grid::make(3)->schema([
                    // LEFT: Payout details (2/3)
                    \Filament\Schemas\Components\Group::make()->columnSpan(2)->schema([
                        Section::make(__('Decont'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Infolists\Components\TextEntry::make('decont_overview')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderDecontOverview($record))
                                    ->html()
                                    ->columnSpanFull(),
                                \Filament\Schemas\Components\Actions::make([
                                        \Filament\Actions\Action::make('edit_decont_series_inline')
                                            ->label(__('Editează seria'))
                                            ->icon('heroicon-m-pencil-square')
                                            ->color('gray')
                                            ->modalHeading(__('Editează seria decontului'))
                                            ->modalDescription(__('Tastează doar partea după prefix (ex. „1204" → DECAMB1204). Prefixul vine automat din Settings → Personalization. Lasă gol pentru a șterge seria.'))
                                            ->form([
                                                \Filament\Forms\Components\TextInput::make('decont_series')
                                                    ->label(__('Serie decont'))
                                                    ->maxLength(40)
                                                    ->prefix(function ($record) {
                                                        $settings = $record->marketplaceClient?->settings ?? [];
                                                        return $settings['decont_prefix'] ?? 'DEC';
                                                    })
                                                    ->default(function ($record) {
                                                        // Show just the suffix in the input when the saved
                                                        // value already starts with the configured prefix
                                                        // — so the visual prefix doesn't double up.
                                                        $current = (string) ($record->decont_series ?? '');
                                                        $settings = $record->marketplaceClient?->settings ?? [];
                                                        $prefix = (string) ($settings['decont_prefix'] ?? 'DEC');
                                                        if ($prefix !== '' && str_starts_with($current, $prefix)) {
                                                            return substr($current, strlen($prefix));
                                                        }
                                                        return $current;
                                                    }),
                                            ])
                                            ->action(function (array $data, $record, $livewire) {
                                                $suffix = trim((string) ($data['decont_series'] ?? ''));
                                                if ($suffix === '') {
                                                    $record->updateQuietly(['decont_series' => null]);
                                                } else {
                                                    $settings = $record->marketplaceClient?->settings ?? [];
                                                    $prefix = (string) ($settings['decont_prefix'] ?? 'DEC');
                                                    // If the operator already typed the prefix in the suffix
                                                    // (e.g. pasted the full series), don't double it.
                                                    $full = ($prefix !== '' && str_starts_with($suffix, $prefix))
                                                        ? $suffix
                                                        : $prefix . $suffix;
                                                    $record->updateQuietly(['decont_series' => $full]);
                                                }
                                                $livewire->refreshFormData(['decont_series']);
                                                \Filament\Notifications\Notification::make()
                                                    ->title(__('Serie actualizată'))
                                                    ->success()
                                                    ->send();
                                            })
                                        ->link()
                                        ->size('sm'),
                                ])->extraAttributes(['data-ep-edit-series' => '1', 'style' => 'display:none']),
                            ])
                            ->columns(1),

                        // Ticket breakdown table — the blade injects CSS that zeros padding
                        // on the fi-section-content inside this specific Section (tagged
                        // with the ep-breakdown-section class) so the table sits flush with
                        // the card edges.
                        Section::make(__('Detalii bilete'))
                            ->icon('heroicon-o-ticket')
                            ->afterHeader(fn ($livewire) => static::ticketActionsHtml($livewire))
                            ->schema([
                                Infolists\Components\ViewEntry::make('ticket_breakdown_view')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.payout-ticket-breakdown')
                                    ->columnSpanFull(),
                            ])
                            ->extraAttributes(['class' => 'ep-breakdown-section'])
                            // Also show when there are only refunds and no valid tickets left
                            // (fully-refunded payouts like PAY-DC3HSEDV-3125 whose snapshot is
                            // NULL). The blade renders the "Bilete rambursate" subsection
                            // independently of $ticket_breakdown so the operator can still see
                            // the returned tickets even when Detalii bilete's main table has
                            // nothing to render.
                            ->visible(fn ($record) => !empty($record->ticket_breakdown)
                                || (float) ($record->refund_amount ?? 0) > 0.01),

                        // Financial summary — split clearly into two parts:
                        //   1. "Acest decont"    — values for THIS payout only (from
                        //      ticket_breakdown JSON, via getBreakdownTotals).
                        //   2. "Total eveniment" — aggregated event-level view (sum
                        //      of all payouts + remaining balance, in a separate
                        //      section below).
                        // Previously these two views were mixed in the same block,
                        // so "Vânzări totale eveniment" (event-level) appeared next
                        // to "Suma brută decont bilete online" (payout-level) and
                        // the contradictory numbers confused operators.
                        Section::make(__('Sume'))
                            ->icon('heroicon-o-calculator')
                            ->description(__('Stânga: doar acest decont. Dreapta: tot evenimentul, pentru context.'))
                            ->schema([
                                Infolists\Components\TextEntry::make('money_overview')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderMoneyOverview($record))
                                    ->html()
                                    ->columnSpanFull(),
                            ]),

                    ]),

                    // RIGHT: Sidebar (1/3)
                    \Filament\Schemas\Components\Group::make()->columnSpan(1)->schema([
                        // Status workflow actions (approve / process / complete /
                        // reject) + Admin Note. Moved here from the page header so
                        // the operator's main controls live alongside the payout
                        // context instead of getting buried under the document
                        // generation buttons up top.
                        Section::make(function ($record) {
                            $n = count(array_filter([$record->organizer_notes, $record->admin_notes, $record->decont_mentions], fn ($v) => trim((string) $v) !== ''));

                            return $n > 0 ? "Note ({$n})" : 'Note';
                        })
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->iconColor(fn ($record) => (trim((string) $record->organizer_notes) . trim((string) $record->admin_notes) . trim((string) $record->decont_mentions)) !== '' ? 'warning' : 'gray')
                            ->description(function ($record) {
                                $n = count(array_filter([$record->organizer_notes, $record->admin_notes, $record->decont_mentions], fn ($v) => trim((string) $v) !== ''));

                                return $n > 0 ? ($n === 1 ? '1 notă completată' : "{$n} note completate") : 'nicio notă';
                            })
                            ->compact()
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Infolists\Components\TextEntry::make('notes_view')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderNotes($record))
                                    ->html(),
                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('add_admin_note')
                                        ->label(__('Notă admin'))
                                        ->icon('heroicon-o-pencil-square')
                                        ->color('gray')
                                        ->form([
                                            \Filament\Forms\Components\Textarea::make('admin_notes')
                                                ->label(__('Note admin'))
                                                ->default(fn ($record) => $record->admin_notes)
                                                ->rows(3),
                                        ])
                                        ->action(function (array $data, $record, $livewire) {
                                            $record->update(['admin_notes' => $data['admin_notes']]);
                                            $livewire->refreshFormData(['admin_notes']);
                                        }),
                                    \Filament\Actions\Action::make('edit_decont_mentions')
                                        ->label(__('Mențiuni decont'))
                                        ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                        ->color('gray')
                                        ->modalHeading(__('Mențiuni decont'))
                                        ->modalDescription(__('Textul apare în PDF-ul decontului, sub nota „Facturile pot circula fără semnătură...”. Lasă gol ca să nu apară nimic.'))
                                        ->visible(fn ($record) => ! in_array($record->status, ['rejected', 'cancelled'], true))
                                        ->fillForm(fn ($record) => [
                                            'decont_mentions' => $record->decont_mentions,
                                            'regenerate' => $record->decontDocument !== null,
                                        ])
                                        ->form([
                                            \Filament\Forms\Components\Textarea::make('decont_mentions')
                                                ->label(__('Mențiuni'))
                                                ->rows(4)
                                                ->maxLength(2000),
                                            \Filament\Forms\Components\Toggle::make('regenerate')
                                                ->label(__('Regenerează decontul acum'))
                                                ->helperText(__('Numărul decontului rămâne același.'))
                                                ->visible(fn ($record) => $record->decontDocument !== null),
                                        ])
                                        ->action(function (array $data, $record, $livewire) {
                                            $mentions = trim((string) ($data['decont_mentions'] ?? ''));
                                            $record->update(['decont_mentions' => $mentions !== '' ? $mentions : null]);

                                            if (! empty($data['regenerate']) && $record->decontDocument !== null
                                                && method_exists($livewire, 'regenerateDecontDocument')) {
                                                $livewire->regenerateDecontDocument();
                                                return;
                                            }

                                            \Filament\Notifications\Notification::make()
                                                ->title(__('Mențiuni salvate'))
                                                ->body($record->decontDocument !== null ? 'Regenerează decontul ca să apară în PDF.' : null)
                                                ->success()
                                                ->send();
                                            $livewire->redirect(static::getUrl('view', ['record' => $record]));
                                        }),
                                ]),
                            ]),

                        Section::make(__('Acțiuni'))
                            ->icon('heroicon-o-bolt')
                            ->compact()
                            ->description(fn ($record) => in_array($record->status, ['approved', 'processing'], true)
                                ? 'De transferat: ' . number_format($record->transferAmount(), 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                                : null)
                            ->columns(1)
                            ->schema([
                                // Sus pasul principal (Aprobă / Finalizează), apoi Respinge,
                                // restul într-un singur meniu „Alte acțiuni”. „În procesare” nu
                                // mai apare: Finalizează merge direct din Aprobat, iar statusul
                                // rămâne valid pentru deconturile vechi.
                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('approve_payout')
                                        ->label(__('Aprobă'))
                                        ->icon('heroicon-o-check')
                                        ->color('success')
                                        ->requiresConfirmation()
                                        ->visible(fn ($record) => $record->canBeApproved())
                                        ->action(function ($record, $livewire) {
                                            $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
                                            $record->approve($admin->id);
                                            $livewire->redirect(static::getUrl('view', ['record' => $record]));
                                        }),
                                ])->fullWidth(),

                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('complete_payout')
                                        ->label(__('Finalizează'))
                                        ->icon('heroicon-o-check-circle')
                                        ->color('success')
                                        ->visible(fn ($record) => $record->canBeCompleted())
                                        // Decont acoperit (parțial) dintr-un avans: se transferă doar restul.
                                        ->modalDescription(function ($record) {
                                            $covered = $record->advanceCoveredAmount();
                                            $invoiced = $record->invoicedCommission();
                                            if ($covered <= 0 && $invoiced <= 0.005) {
                                                return null;
                                            }
                                            $cur = ' ' . ($record->currency ?? 'RON');
                                            $text = 'Sumă decont: ' . number_format((float) $record->amount, 2, ',', '.') . $cur . '.';
                                            if ($covered > 0) {
                                                $text .= ' Compensat din avans: ' . number_format($covered, 2, ',', '.') . $cur . '.';
                                            }
                                            if ($invoiced > 0.005) {
                                                $text .= ' Comision inclus nereținut (pe factura organizator): +' . number_format($invoiced, 2, ',', '.') . $cur . '.';
                                            }

                                            return $text . ' De transferat: ' . number_format($record->transferAmount(), 2, ',', '.') . $cur . '.';
                                        })
                                        ->fillForm(fn ($record) => ($record->advanceCoveredAmount() > 0 && $record->transferAmount() <= 0.004)
                                            ? ['payment_reference' => 'Compensat integral din avans']
                                            : [])
                                        ->form([
                                            \Filament\Forms\Components\TextInput::make('payment_reference')
                                                ->label(__('Referință plată'))
                                                ->required()
                                                ->helperText(__('Referința transferului bancar sau ID-ul tranzacției')),
                                            \Filament\Forms\Components\Textarea::make('payment_notes')
                                                ->label(__('Note'))
                                                ->rows(2),
                                        ])
                                        ->action(function (array $data, $record, $livewire) {
                                            $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                                            $livewire->redirect(static::getUrl('view', ['record' => $record]));
                                        }),
                                ])->fullWidth(),

                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('reject_payout')
                                        ->label(__('Respinge'))
                                        ->icon('heroicon-o-x-circle')
                                        ->color('danger')
                                        ->outlined()
                                        ->visible(fn ($record) => $record->canBeRejected())
                                        ->form([
                                            \Filament\Forms\Components\Textarea::make('reason')
                                                ->label(__('Motiv respingere'))
                                                ->required()
                                                ->rows(3),
                                        ])
                                        ->action(function (array $data, $record, $livewire) {
                                            $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
                                            $record->reject($admin->id, $data['reason']);

                                            $decont = $record->decontDocument;
                                            if ($decont) {
                                                if ($decont->file_path) {
                                                    \Illuminate\Support\Facades\Storage::disk('public')->delete($decont->file_path);
                                                }
                                                $decont->delete();
                                            }

                                            $invoice = $record->invoice;
                                            if ($invoice) {
                                                $invoice->delete();
                                            }

                                            \Filament\Notifications\Notification::make()
                                                ->title(__('Decont respins'))
                                                ->body(__('Documentele asociate au fost șterse.'))
                                                ->success()
                                                ->send();
                                            $livewire->redirect(static::getUrl('view', ['record' => $record]));
                                        }),
                                ])->fullWidth(),

                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\ActionGroup::make([
                                    \Filament\Actions\Action::make('set_net_override')
                                        ->label(__('Ajustare manuală net'))
                                        ->icon('heroicon-o-adjustments-horizontal')
                                        ->color('warning')
                                        ->modalHeading(__('Ajustare manuală a netului de plată'))
                                        ->modalDescription(__('Setează manual suma netă de plată când calculul automat e greșit (ex. decont ajustat pentru fraudă). Lasă gol pentru a reveni la calculul automat din bilete.'))
                                        ->form([
                                            \Filament\Forms\Components\TextInput::make('net_override')
                                                ->label(__('Net de plată (override)'))
                                                ->numeric()
                                                ->step('0.01')
                                                ->suffix('RON')
                                                ->default(fn ($record) => $record->net_override)
                                                ->helperText(__('Gol = calcul automat din bilete.')),
                                            \Filament\Forms\Components\Textarea::make('override_reason')
                                                ->label(__('Motiv (se adaugă la notele admin)'))
                                                ->rows(2),
                                        ])
                                        ->action(function (array $data, $record, $livewire) {
                                            $val = ($data['net_override'] === null || $data['net_override'] === '')
                                                ? null
                                                : round((float) $data['net_override'], 2);
                                            $note = trim(($record->admin_notes ?? '') . "\n[" . now()->format('Y-m-d') . '] Net override '
                                                . ($val === null ? 'eliminat' : ('setat la ' . $val))
                                                . (!empty($data['override_reason']) ? (': ' . $data['override_reason']) : ''));
                                            $record->update(['net_override' => $val, 'admin_notes' => $note]);
                                            \Filament\Notifications\Notification::make()
                                                ->title(__('Ajustare aplicată'))
                                                ->success()
                                                ->send();
                                            $livewire->refreshFormData(['net_override', 'admin_notes']);
                                        }),
                                    \Filament\Actions\Action::make('delete_payout')
                                        ->label(__('Șterge decont'))
                                        ->icon('heroicon-o-trash')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading(__('Șterge decontul'))
                                        ->modalDescription(__('Decontul va fi mutat în coșul de gunoi (soft delete). Documentele asociate (PDF decont, factură, factură organizator) NU se șterg automat — ocupă-te de ele separat dacă e nevoie.'))
                                        ->modalSubmitActionLabel(__('Da, șterge'))
                                        ->visible(fn ($record): bool => $record->status !== 'completed')
                                        ->action(function ($record, $livewire) {
                                            $record->delete();
                                            \Filament\Notifications\Notification::make()
                                                ->title(__('Decont șters'))
                                                ->success()
                                                ->send();
                                            $livewire->redirect(static::getUrl('index'));
                                        }),
                                    ])
                                        ->label(__('Alte acțiuni'))
                                        ->icon('heroicon-m-ellipsis-horizontal')
                                        ->color('gray')
                                        ->button(),
                                ])->fullWidth(),
                            ]),

                        Section::make(__('Documente'))
                            ->icon('heroicon-o-document-duplicate')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('documents_view')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderDocuments($record))
                                    ->html(),
                            ])
                            ->visible(fn ($record) => ! in_array($record->status, ['rejected', 'cancelled'], true)),

                        // Event decont context
                        Section::make(__('Context eveniment'))
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('event_context_view')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderEventContext($record))
                                    ->html(),
                            ])
                            ->visible(fn ($record) => $record->event_id),

                        // Timeline
                        Section::make(__('Cronologie'))
                            ->icon('heroicon-o-clock')
                            ->compact()
                            ->schema([
                                Infolists\Components\TextEntry::make('timeline_view')
                                    ->hiddenLabel()
                                    ->getStateUsing(fn () => '—')
                                    ->formatStateUsing(fn ($state, $record) => static::renderTimeline($record))
                                    ->html(),
                            ]),

                    ]),
                ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn ($record) => $record->decont_series)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('decont_series')
                    ->label(__('Serie decont'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label(__('Organizer'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('organizer', function ($q) use ($term) {
                            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                              ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$term]);
                        });
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label(__('Event'))
                    ->placeholder(__('General'))
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state['ro'] ?? $state['en'] ?? reset($state) ?? 'Untitled')
                        : $state)
                    ->limit(25)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('event', function ($q) use ($term) {
                            $isPgsql = \DB::getDriverName() === 'pgsql';
                            $q->whereRaw(
                                $isPgsql
                                    ? "LOWER(title::jsonb->>'ro') LIKE ? OR LOWER(title::jsonb->>'en') LIKE ?"
                                    : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?",
                                [$term, $term]
                            );
                        });
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    // Show the FINAL net (row E in the decont PDF) — gross
                    // − discounts − refunds − advance — so the column
                    // matches what the organizer actually receives. The
                    // raw `amount` field stays as gross on the row for
                    // back-compat with other consumers; we override the
                    // displayed state only.
                    ->state(fn ($record) => $record->final_net_amount)
                    ->money('RON')
                    ->sortable(query: function ($query, string $direction) {
                        // Sort still happens on the DB column (close enough
                        // to the displayed net for ordering purposes).
                        return $query->orderBy('amount', $direction);
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'organizer' => 'primary',
                        'manual' => 'warning',
                        'automated' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'organizer' => 'Organizer',
                        'manual' => 'Admin',
                        // Legacy 'automated' rows were created by an admin
                        // clicking "Generează decont" — there is no real
                        // background-cron source. Show as Admin for clarity.
                        'automated' => 'Admin',
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(timezone: MarketplaceTz::tz())
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime(timezone: MarketplaceTz::tz())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'approved' => __('Approved'),
                        'processing' => __('Processing'),
                        'completed' => __('Completed'),
                        'rejected' => __('Rejected'),
                        'cancelled' => __('Cancelled'),
                    ]),

                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeApproved())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->approve($admin->id);
                    }),

                Action::make('process')
                    ->label(__('Mark Processing'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeProcessed())
                    ->action(function (MarketplacePayout $record): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->markAsProcessing($admin->id);
                    }),

                Action::make('complete')
                    ->label(__('Complete'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeCompleted())
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label(__('Payment Reference'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label(__('Payment Notes'))
                            ->rows(2),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    }),

                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplacePayout $record): bool => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label(__('Rejection Reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MarketplacePayout $record, array $data): void {
                        $admin = Auth::guard('marketplace_admin')->user();
                        $record->reject($admin->id, $data['rejection_reason']);

                        // Delete associated documents
                        $decont = $record->decontDocument;
                        if ($decont) {
                            if ($decont->file_path) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($decont->file_path);
                            }
                            $decont->delete();
                        }
                        if ($record->invoice) {
                            $record->invoice->delete();
                        }
                    }),

                ViewAction::make(),

                Action::make('delete')
                    ->label(__('Șterge'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('Decontul va fi șters, iar suma rezervată va reveni în soldul organizatorului.'))
                    ->visible(fn (MarketplacePayout $record): bool => $record->status !== 'completed')
                    ->action(function (MarketplacePayout $record): void {
                        static::reversePayout($record);
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_delete')
                        ->label(__('Șterge selectate'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('Deconturile selectate vor fi șterse, iar sumele rezervate vor reveni în soldul organizatorilor. Deconturile finalizate nu se șterg.'))
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            foreach ($records as $record) {
                                static::reversePayout($record);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }

    /**
     * Reverse a payout: return balance, delete related documents, invoices, transactions.
     */
    public static function reversePayout(MarketplacePayout $payout): void
    {
        // The money already left: reversing would restore the balance and allow a second payment.
        if ($payout->status === 'completed') {
            \Filament\Notifications\Notification::make()
                ->title(__('Decontul finalizat nu poate fi șters'))
                ->body("Decontul {$payout->reference} a fost deja plătit.")
                ->warning()
                ->send();

            return;
        }

        \DB::beginTransaction();
        try {
            $organizer = $payout->organizer;

            // 1. Return the reserved cash to the organizer, then free the advance it consumed
            if (in_array($payout->status, ['approved', 'processing']) && $organizer) {
                $organizer->returnPendingBalance($payout->cashAmount());
            }
            $payout->releaseAdvanceAllocations();

            // 2. Delete decont document + PDF file
            $decont = $payout->decontDocument;
            if ($decont) {
                if ($decont->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($decont->file_path);
                }
                $decont->delete();
            }

            // 3. Delete associated invoice
            $invoice = $payout->invoice;
            if ($invoice) {
                $invoice->delete();
            }

            // 4. Delete transactions
            $payout->transactions()->delete();

            // 5. Delete the payout itself
            $payout->delete();

            \DB::commit();

            \Filament\Notifications\Notification::make()
                ->title(__('Decont șters'))
                ->body(__('Valorile au fost returnate organizatorului.'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to reverse payout', ['payout_id' => $payout->id, 'error' => $e->getMessage()]);

            \Filament\Notifications\Notification::make()
                ->title(__('Eroare la ștergere'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * „Acest decont”: un singur calcul citit de sus în jos — vânzări, ce se
     * scade, suma de plată. Suma finală e final_net_amount (aceeași ca PDF-ul).
     */
    public static function renderPayoutSummary(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $t = $record->getBreakdownTotals();
        $cur = ' ' . ($record->currency ?? 'RON');
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . $cur;
        $neg = fn ($v) => '−' . $fmt($v);
        $invoiced = $record->invoicedCommission() > 0.005;
        $isOnTop = ($record->commission_mode ?? 'included') === 'added_on_top';

        $line = fn (string $label, string $value, string $note = '', string $tone = '') => static::uiLine($label, $value, $note, $tone);
        $group = fn (string $title, string $body) => static::uiGroup($title, $body);
        $subtotal = fn (string $label, $v) => static::uiSubtotal($label, $fmt($v));

        // Bilete online
        $o = $t['online'];
        $online = '';
        if ($o['gross'] > 0.005 || $o['commission'] > 0.005) {
            $online .= $line('Vânzări (ce au plătit clienții)', $fmt($o['gross']));
            if ($o['commission'] > 0.005) {
                if ($invoiced) {
                    $inv = $record->commissionInvoice();
                    $note = $inv
                        ? 'nu se reține; se facturează separat: <a href="' . e(\App\Filament\Marketplace\Resources\OrganizerInvoiceResource::getUrl('edit', ['record' => $inv])) . '" style="color:#2563eb;font-weight:500;">' . e($inv->number) . '</a> = ' . $fmt($inv->amount) . ' cu TVA'
                        : 'nu se reține; se facturează separat (factura nu e generată încă)';
                    $online .= $line('Comision inclus în preț', $fmt($o['commission']), $note, 'muted');
                } else {
                    $online .= $line($isOnTop ? 'Comision adăugat peste preț' : 'Comision', $neg($o['commission']), '', 'minus');
                }
            }
            if ($o['discount'] > 0.005) {
                $online .= $line('Reduceri', $neg($o['discount']), '', 'minus');
            }
            if ($o['extras'] > 0.005) {
                $online .= $line('Extras', $neg($o['extras']), 'asigurări bilete, suprataxe', 'minus');
            }
            $online .= $subtotal('Net bilete online', $o['net'] + ($invoiced ? $record->invoicedCommission() : 0));
        }

        // Bilete vândute prin aplicație
        $p = $t['pos'];
        $pos = '';
        if ($p['gross'] > 0.005) {
            $pos .= $line('Vânzări', $fmt($p['gross']));
            if ($p['commission'] > 0.005) {
                $pos .= $line('Comision', $neg($p['commission']), '', 'minus');
            }
            if ($p['discount'] > 0.005) {
                $pos .= $line('Reduceri', $neg($p['discount']), '', 'minus');
            }
            $pos .= $subtotal('Net bilete aplicație', $p['net']);
        }

        // Ce se mai scade / adaugă
        $other = '';
        $deductible = (float) $record->deductible_refund_amount;
        $informational = (float) $record->informational_refund_amount;
        if ($deductible > 0.005) {
            $note = $record->refundsAlreadyExcludedFromNet() ? 'biletele sunt deja scoase din net'
                : ($record->usesAutoRefunds() ? 'bilete plătite într-un decont anterior' : '');
            $other .= $line('Rambursări', $neg($deductible), $note, 'minus');
        }
        if ($informational > 0.005) {
            if ($record->usesAutoRefunds() || $isOnTop) {
                $other .= $line('Rambursări raportate', $fmt($informational), 'informativ, nu se scad', 'muted');
            } else {
                $other .= $line('Rambursări', $neg($informational), '', 'minus');
            }
        }
        $advance = (float) (($record->payout_method['advance_amount'] ?? null) ?? 0);
        if ($advance > 0.005) {
            $other .= $line('Avans dedus', $neg($advance), '', 'minus');
        }
        if ((float) $record->fees_amount > 0.005) {
            $other .= $line('Taxe', $neg($record->fees_amount), '', 'minus');
        }
        if (abs((float) $record->adjustments_amount) > 0.005) {
            $adj = (float) $record->adjustments_amount;
            $other .= $line('Ajustări', ($adj >= 0 ? '+' : '−') . $fmt(abs($adj)), e((string) $record->adjustments_note), $adj >= 0 ? '' : 'minus');
        }

        $final = (float) $record->final_net_amount;
        $modeLabel = match ($record->commission_mode) {
            'included' => 'comision inclus în preț',
            'added_on_top' => 'comision adăugat peste preț',
            default => 'mod comision nesetat',
        };
        $recipient = match ($record->invoice_recipient_type) {
            'organizer' => 'factura pe organizator',
            'general_client' => 'factura pe client general',
            default => null,
        };
        $meta = e(implode(' · ', array_filter([$modeLabel, $recipient])));

        return static::uiWrap(
            $group('Bilete online', $online),
            $group('Bilete vândute prin aplicație', $pos),
            $group('Alte sume', $other),
            static::paymentTotal($record, $fmt($final), $meta)
        );
    }

    // ── Afișare comună pentru secțiunile paginii de decont (același stil peste tot).
    // Etichetele și valorile primite sunt deja HTML sigur (escapat de apelant).

    public static function uiLine(string $label, string $value, string $note = '', string $tone = ''): string
    {
        $color = match ($tone) {
            'minus' => '#dc2626',
            'muted' => '#9ca3af',
            'success' => '#059669',
            'warning' => '#d97706',
            default => 'inherit',
        };

        return "<div style='display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:5px 0;'>"
            . "<div style='min-width:0;'><div style='font-size:13px;'>{$label}</div>"
            . ($note !== '' ? "<div style='font-size:11px;color:#9ca3af;margin-top:1px;'>{$note}</div>" : '')
            . "</div><div style='font-size:13px;font-variant-numeric:tabular-nums;text-align:right;color:{$color};'>{$value}</div></div>";
    }

    public static function uiGroup(string $title, string $body): string
    {
        if ($body === '') {
            return '';
        }

        return "<div>"
            . "<div style='font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;'>{$title}</div>{$body}</div>";
    }

    public static function uiSubtotal(string $label, string $value): string
    {
        return "<div style='display:flex;justify-content:space-between;gap:16px;padding:6px 0 0;margin-top:2px;border-top:1px dashed rgba(127,127,127,.25);font-weight:600;font-size:13px;'>"
            . "<div>{$label}</div><div style='font-variant-numeric:tabular-nums;white-space:nowrap;'>{$value}</div></div>";
    }

    public static function uiTotal(string $label, string $value, string $note = '', string $tone = 'success'): string
    {
        [$fg, $bg, $bd] = $tone === 'success'
            ? ['#059669', 'rgba(5,150,105,.08)', 'rgba(5,150,105,.35)']
            : ['inherit', 'rgba(127,127,127,.06)', 'rgba(127,127,127,.25)'];

        return "<div style='display:flex;justify-content:space-between;align-items:baseline;gap:16px;padding:12px 14px;border-radius:8px;background:{$bg};border:1px solid {$bd};'>"
            . "<div><div style='font-size:13px;font-weight:600;color:{$fg};'>{$label}</div>"
            . ($note !== '' ? "<div style='font-size:11px;color:#9ca3af;margin-top:2px;'>{$note}</div>" : '')
            . "</div><div style='font-size:18px;font-weight:700;color:{$fg};font-variant-numeric:tabular-nums;white-space:nowrap;'>{$value}</div></div>";
    }

    public static function uiWrap(string ...$parts): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            "<div style='display:flex;flex-direction:column;gap:14px;width:100%;'>" . implode('', array_filter($parts)) . '</div>'
        );
    }

    public static function uiChip(string $text, string $color): string
    {
        return "<span style='display:inline-block;padding:1px 8px;border-radius:9999px;background:{$color}1f;color:{$color};font-size:11px;font-weight:600;white-space:nowrap;'>{$text}</span>";
    }

    protected static function uiMoney($v, string $cur = 'RON'): string
    {
        return number_format((float) $v, 2, ',', '.') . ' ' . $cur;
    }

    /** Context eveniment: toate deconturile evenimentului + soldul rămas */
    public static function renderEventContext(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $payouts = MarketplacePayout::where('event_id', $record->event_id)
            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->orderBy('created_at')
            ->get(['id', 'reference', 'decont_series', 'amount', 'invoiced_commission_amount', 'status', 'created_at', 'marketplace_client_id']);


        $rows = '';
        $nr = 0;
        foreach ($payouts as $i => $p) {
            $isCurrent = $p->id === $record->id;
            if ($isCurrent) {
                $nr = $i + 1;
            }
            $ref = e($p->decont_series ?: $p->reference);
            $title = $isCurrent
                ? "<span style='font-weight:600;'>{$ref}</span> <span style='font-size:11px;color:#059669;font-weight:600;'>acest decont</span>"
                : "<a href='" . e(static::getUrl('view', ['record' => $p->id])) . "' style='color:#2563eb;font-weight:500;'>{$ref}</a>";
            $date = MarketplaceTz::fmt($p->created_at, 'd.m.Y', null, fallback: '—');
            $amount = (float) $p->amount + (float) ($p->invoiced_commission_amount ?? 0);
            $rows .= static::uiLine(
                $title,
                static::uiMoney($amount),
                e($date) . ' &nbsp;' . static::statusChip((string) $p->status)
            );
        }

        $title = $payouts->count() > 0 ? "Deconturi pe eveniment ({$nr} din {$payouts->count()})" : 'Deconturi pe eveniment';

        return static::uiWrap(static::uiGroup($title, $rows ?: static::uiLine('—', '')));
    }

    /** Cronologie */
    public static function renderTimeline(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $tz = MarketplaceTz::tz();
        $dt = fn ($d) => $d ? e($d->copy()->timezone($tz)->format('d.m.Y H:i')) : null;

        $createdAt = static::createdMoment($record);
        $creator = static::creatorText($record);

        // Deconturile admin se aprobă chiar la creare; cele cerute de organizator le aprobă un admin.
        $approvedNote = '';
        if ($record->approved_at) {
            $approvedNote = $record->source === 'organizer'
                ? ($record->approvedByUser ? 'de ' . e($record->approvedByUser->name) . ' (admin)' : '')
                : 'automat, la creare';
        }

        $docDate = ($record->created_at && $createdAt && $record->created_at->format('Y-m-d') !== $createdAt->format('Y-m-d'))
            ? $record->created_at->format('d.m.Y') : null;
        $createdNote = implode(' · ', array_filter([
            $creator ? 'de ' . e($creator) : null,
            $docDate ? 'data pe document: ' . $docDate : null,
        ]));

        // Cine a marcat plătit: salvat din 10.10.2026 (completed_by).
        // Finalizat = plătit, indiferent de unde s-a marcat (pagina decontului
        // sau Lista deconturi din Sold organizator): ambele trec prin complete().
        $completedNote = '';
        if ($record->completed_at) {
            $who = static::adminName($record->completed_by ?? null);
            $whoText = $who ? 'marcat de ' . e($who) . ' (admin)'
                : ($record->completed_at < '2026-10-10' ? 'cine a marcat: neînregistrat (înainte de 10.10.2026)' : null);
            $completedNote = implode(' · ', array_filter([
                static::uiMoney($record->transferAmount(), $record->currency ?? 'RON'),
                $whoText,
                $record->payment_reference ? 'ref. ' . e($record->payment_reference) : null,
            ]));
        }

        $steps = [
            ['Creat', $dt($createdAt), $createdNote],
            ['Aprobat', $dt($record->approved_at), $approvedNote],
            ['Procesat', $dt($record->processed_at), ''],
            ['Plătit', $dt($record->completed_at), $completedNote],
        ];

        $items = '';
        foreach ($steps as [$label, $when, $note]) {
            if ($label !== 'Creat' && ! $when) {
                continue;
            }
            $items .= "<div style='position:relative;padding:0 0 12px 18px;'>"
                . "<span style='position:absolute;left:-5px;top:5px;width:8px;height:8px;border-radius:9999px;background:#059669;'></span>"
                . "<div style='display:flex;justify-content:space-between;gap:12px;font-size:13px;'><span>{$label}</span><span style='font-variant-numeric:tabular-nums;color:#6b7280;'>" . ($when ?? '—') . '</span></div>'
                . ($note !== '' ? "<div style='font-size:11px;color:#9ca3af;margin-top:1px;'>{$note}</div>" : '')
                . '</div>';
        }
        $timeline = "<div style='border-left:1px solid rgba(127,127,127,.25);margin-left:3px;padding-left:0;'>"
            . $items . '</div>';

        $period = $record->period_start
            ? static::uiLine('Perioadă vânzări', e(($record->period_start?->format('d.m.Y') ?? '—') . ' → ' . ($record->period_end?->format('d.m.Y') ?? '—')))
            : '';

        return static::uiWrap($timeline, $period);
    }

    public static function statusChip(string $status): string
    {
        $color = ['pending' => '#d97706', 'approved' => '#2563eb', 'processing' => '#7c3aed', 'completed' => '#059669', 'rejected' => '#dc2626', 'cancelled' => '#6b7280'][$status] ?? '#6b7280';
        $label = ['pending' => 'În așteptare', 'approved' => 'Aprobat', 'processing' => 'În procesare', 'completed' => 'Finalizat', 'rejected' => 'Respins', 'cancelled' => 'Anulat'][$status] ?? e($status);

        return "<span style='display:inline-block;padding:1px 8px;border-radius:9999px;background:{$color};color:#fff;font-size:11px;font-weight:600;white-space:nowrap;'>{$label}</span>";
    }

    /** Culoarea iconițelor care declanșează acțiuni (să iasă în evidență). */
    public const ACTION_ICON_COLOR = '#d97706';

    protected static function iconSvg(string $path, int $size = 16): string
    {
        return "<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.8' stroke='currentColor' width='{$size}' height='{$size}' style='display:block;'><path stroke-linecap='round' stroke-linejoin='round' d='{$path}'/></svg>";
    }

    protected static function copyButton(string $value): string
    {
        $js = e(json_encode($value));
        $copy = static::iconSvg('M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75', 15);
        $check = e(json_encode(static::iconSvg('m4.5 12.75 6 6 9-13.5', 15)));
        $color = static::ACTION_ICON_COLOR;

        return " <button type='button' title='Copiază' aria-label='Copiază'"
            . " onclick=\"navigator.clipboard.writeText({$js});const o=this.innerHTML;this.innerHTML={$check};setTimeout(()=>this.innerHTML=o,1200)\""
            . " style='display:inline-flex;vertical-align:-3px;margin-left:6px;color:{$color};cursor:pointer;'>{$copy}</button>";
    }

    /**
     * Cine a creat decontul, la fel peste tot: „Cati (admin)” sau
     * „Nume organizator (organizator)”. Aprobarea e alt pas, cu omul ei.
     */
    public static function creatorText(MarketplacePayout $record): ?string
    {
        if ($record->source === 'organizer') {
            return $record->organizer ? $record->organizer->name . ' (organizator)' : 'organizator';
        }
        if (($admin = static::adminName($record->created_by ?? null)) !== null) {
            return $admin . ' (admin)';
        }
        if ($record->approvedByUser) {
            return $record->approvedByUser->name . ' (admin)';
        }
        if ($record->source === 'automated' && $record->created_at && $record->created_at < '2026-05-05') {
            return 'un admin (nume neînregistrat)';
        }
        if ($record->source === 'automated') {
            return 'automat (sistem)';
        }

        return null;
    }

    protected static function adminName($id): ?string
    {
        if (! $id) {
            return null;
        }
        static $cache = [];

        return $cache[$id] ??= \App\Models\MarketplaceAdmin::find($id)?->name;
    }

    /** Momentul real al creării (created_at poate fi antedatat la deconturile admin). */
    public static function createdMoment(MarketplacePayout $record)
    {
        return (in_array($record->source, ['manual', 'automated'], true) && $record->approved_at)
            ? $record->approved_at : $record->created_at;
    }

    /** Secțiunea Decont: identitatea decontului + contul bancar. */
    public static function renderDecontOverview(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $link = fn (string $text, ?string $url) => $url
            ? "<a href='" . e($url) . "' style='color:#2563eb;font-weight:500;'>{$text}</a>" : $text;

        $series = $record->decont_series
            ? "<span style='font-weight:600;'>" . e($record->decont_series) . '</span>'
            : "<span style='color:#9ca3af;'>fără serie</span>";
        $ref = $record->reference ? e($record->reference) . static::copyButton($record->reference) : '—';

        $orgName = $record->organizer?->name;
        $org = $orgName
            ? $link(e($orgName), $record->marketplace_organizer_id ? OrganizerResource::getUrl('edit', ['record' => $record->marketplace_organizer_id]) : null)
            : '—';

        $eventHtml = 'Decont general';
        if ($record->event) {
            $t = $record->event->title;
            $title = is_array($t) ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: 'Eveniment')) : ($t ?: 'Eveniment');
            $start = $record->event->start_date?->format('d.m.Y');
            $end = $record->event->end_date?->format('d.m.Y');
            $date = $start ? ($end && $end !== $start ? "{$start} – {$end}" : $start) : null;
            $eventHtml = $link(e($title), EventResource::getUrl('edit', ['record' => $record->event_id]))
                . ($date ? "<div style='font-size:11px;color:#9ca3af;margin-top:1px;'>" . e($date) . '</div>' : '');
        }

        $color = static::ACTION_ICON_COLOR;
        $pencil = static::iconSvg('m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10', 15);
        $editSeries = "<button type='button' title='Editează seria' aria-label='Editează seria'"
            . " onclick=\"document.querySelector('[data-ep-edit-series] button')?.click()\""
            . " style='display:inline-flex;vertical-align:-3px;margin-right:6px;color:{$color};cursor:pointer;'>{$pencil}</button>";

        $row = fn (string $label, string $value) => "<div style='display:grid;grid-template-columns:96px 1fr;gap:12px;padding:5px 0;font-size:13px;'>"
            . "<div style='color:#9ca3af;'>{$label}</div><div style='min-width:0;overflow-wrap:anywhere;'>{$value}</div></div>";

        $decont = $row('Serie', $editSeries . $series . ' &nbsp;' . static::statusChip((string) $record->status))
            . $row('Referință', $ref)
            . $row('Organizator', $org)
            . $row('Eveniment', $eventHtml);

        $m = $record->payout_method ?? [];
        $iban = ! empty($m['iban'])
            ? "<span style='font-family:ui-monospace,monospace;'>" . e($m['iban']) . '</span>' . static::copyButton($m['iban'])
            : "<span style='color:#9ca3af;'>lipsește</span>";
        $holder = ! empty($m['account_holder']) ? e($m['account_holder']) . static::copyButton($m['account_holder']) : '—';
        $bank = $row('Bancă', e($m['bank_name'] ?? '—'))
            . $row('Titular', $holder)
            . $row('IBAN', $iban);

        return new \Illuminate\Support\HtmlString(
            "<div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px 40px;'>"
            . static::uiGroup('Decont', $decont)
            . static::uiGroup('Cont bancar', $bank)
            . '</div>'
        );
    }

    /**
     * Sume: stânga doar acest decont (calculul complet), dreapta tot evenimentul
     * pentru context. Cele două nu se amestecă.
     */
    public static function renderMoneyOverview(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $label = e($record->decont_series ?: ($record->reference ?: '#' . $record->id));
        $left = "<div style='min-width:0;'>"
            . "<div style='font-size:12px;font-weight:600;margin-bottom:10px;'>Acest decont <span style='font-weight:400;color:#9ca3af;'>· {$label}</span></div>"
            . static::renderPayoutSummary($record)->toHtml()
            . '</div>';

        $right = '';
        $event = static::eventTotalsBody($record);
        if ($event !== '') {
            $right = "<div style='min-width:0;padding:14px 16px;border-radius:10px;background:rgba(127,127,127,.06);border:1px solid rgba(127,127,127,.15);'>"
                . "<div style='font-size:12px;font-weight:600;margin-bottom:2px;'>Tot evenimentul</div>"
                . "<div style='font-size:11px;color:#9ca3af;margin-bottom:10px;'>toate deconturile evenimentului, pentru context</div>"
                . $event
                . '</div>';
        }

        return new \Illuminate\Support\HtmlString(
            "<div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px 32px;align-items:start;'>"
            . $left . $right . '</div>'
        );
    }

    /** Sumele cumulate pe eveniment (o singură coloană). */
    protected static function eventTotalsBody(MarketplacePayout $record): string
    {
        if (! $record->event_id) {
            return '';
        }
        $event = \App\Models\Event::with('ticketTypes')->find($record->event_id);
        if (! $event) {
            return '';
        }

        $f = \App\Filament\Marketplace\Resources\PayoutResource\Pages\ListPayouts::calculateEventFinancials($event);
        $gross = (float) ($f['gross'] ?? 0);
        $commission = (float) ($f['commission'] ?? 0);
        $net = (float) ($f['net'] ?? 0);
        $refunds = (float) ($f['refunds'] ?? 0);
        $paid = (float) ($f['paid'] ?? 0);
        $pending = (float) ($f['pending'] ?? 0);
        $balance = (float) ($f['balance'] ?? 0);

        // Comision înregistrat pe deconturi + comisionul păstrat din rambursări.
        $base = MarketplacePayout::where('event_id', $record->event_id)
            ->where('marketplace_organizer_id', $record->marketplace_organizer_id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed']);
        $payoutCommission = round((float) (clone $base)->sum('commission_amount') + (float) ($f['kept_commission'] ?? 0), 2);
        $remainingCommission = max(0, $commission - $payoutCommission);

        // Comision inclus facturat separat: organizatorul îl primește în decont
        // și îl plătește pe factura de comision.
        $inv = (clone $base)->whereNotNull('invoiced_commission_amount')->get(['status', 'invoiced_commission_amount']);
        $invoicedTotal = round((float) $inv->sum('invoiced_commission_amount'), 2);
        $invoicedPaid = round((float) $inv->where('status', 'completed')->sum('invoiced_commission_amount'), 2);
        $paid += $invoicedPaid;
        $pending += $invoicedTotal - $invoicedPaid;
        $withheld = max(0, round($payoutCommission - $invoicedTotal, 2));
        $count = (clone $base)->count();

        $m = fn ($v) => static::uiMoney($v);

        $sales = static::uiLine('Vânzări totale', $m($gross))
            . static::uiLine('Comision total', $m($commission), '', 'muted')
            . ($refunds > 0.005 ? static::uiLine('Rambursări', '−' . $m($refunds), '', 'minus') : '')
            . static::uiSubtotal($invoicedTotal > 0.005 ? 'Net organizator' : 'Net total', $m($net + $invoicedTotal));

        $settled = static::uiLine('Plătit', $m($paid), '', 'success')
            . ($pending > 0.005 ? static::uiLine('În așteptare', $m($pending), 'include acest decont, dacă nu e plătit', 'warning') : '')
            . (($withheld > 0.005 || $invoicedTotal <= 0.005) ? static::uiLine('Comision reținut', $m($withheld), '', 'muted') : '')
            . ($invoicedTotal > 0.005 ? static::uiLine('Comision facturat separat', $m($invoicedTotal), '', 'muted') : '')
            . static::uiSubtotal('Total decontat', $m($paid + $pending));

        return static::uiWrap(
            static::uiGroup('Vânzări', $sales),
            static::uiGroup('Decontat · ' . $count . ($count === 1 ? ' decont' : ' deconturi'), $settled),
            static::uiTotal('Rămas de decontat', $m($balance), 'comision rămas: ' . $m($remainingCommission), 'neutral')
        )->toHtml();
    }

    /** Note: organizator, admin, mențiuni pe PDF. */
    public static function renderNotes(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $block = function (string $title, ?string $text, string $hint = '') {
            $text = trim((string) $text);
            $body = $text !== ''
                ? "<div style='font-size:13px;white-space:pre-line;overflow-wrap:anywhere;'>" . e($text) . '</div>'
                : "<div style='font-size:13px;color:#9ca3af;'>—</div>";

            return static::uiGroup($title . ($hint !== '' ? " <span style='text-transform:none;letter-spacing:0;font-weight:400;'>· {$hint}</span>" : ''), $body);
        };

        return static::uiWrap(
            $block('Organizator', $record->organizer_notes),
            $block('Admin', $record->admin_notes, 'doar intern'),
            $block('Mențiuni decont', $record->decont_mentions, 'apar în PDF')
        );
    }

    /**
     * Caseta finală din Sume: „De plată” până la plată, apoi „Plătit” cu data,
     * referința transferului, metoda și notele plății.
     */
    protected static function paymentTotal(MarketplacePayout $record, string $amount, string $meta): string
    {
        $paid = $record->status === 'completed';
        if (! $paid && ! $record->payment_reference) {
            return static::uiTotal('De plată organizatorului', $amount, $meta);
        }

        $parts = [];
        if ($record->completed_at) {
            $parts[] = e(MarketplaceTz::fmt($record->completed_at, 'd.m.Y H:i', null));
        }
        if ($record->payment_reference) {
            $parts[] = 'ref. ' . e($record->payment_reference) . static::copyButton($record->payment_reference);
        }
        if ($record->payment_method) {
            $parts[] = e($record->payment_method);
        }
        $note = implode(' · ', $parts);
        if (trim((string) $record->payment_notes) !== '') {
            $note .= "<div style='margin-top:4px;white-space:pre-line;'>" . e($record->payment_notes) . '</div>';
        }
        $note .= "<div style='margin-top:2px;'>{$meta}</div>";

        return static::uiTotal($paid ? 'Plătit organizatorului' : 'De plată organizatorului', $amount, $note);
    }

    /** Documentele decontului: PDF-ul și facturile, cu starea fiecăruia. */
    public static function renderDocuments(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $muted = fn (string $t) => "<span style='color:#9ca3af;'>{$t}</span>";
        $invoiceRow = function (string $title, $inv, string $missing) use ($muted) {
            if (! $inv) {
                return static::uiLine($title, $muted($missing));
            }
            $url = \App\Filament\Marketplace\Resources\OrganizerInvoiceResource::getUrl('edit', ['record' => $inv]);
            $chip = match ($inv->status) {
                'paid' => static::uiChip('încasată', '#059669'),
                'outstanding' => static::uiChip('neîncasată', '#d97706'),
                default => static::uiChip(e((string) $inv->status), '#6b7280'),
            };

            $meta = $inv->meta ?? [];
            $pdf = $meta['accounting']['pdf_url'] ?? $meta['accounting_proforma']['pdf_url'] ?? null;
            $download = $pdf ? ' ' . static::iconLink($pdf, 'Descarcă factura', static::ICON_DOWNLOAD) : '';

            return static::uiLine(
                $title,
                static::uiMoney($inv->amount, $inv->currency ?? 'RON'),
                "<a href='" . e($url) . "' style='color:#2563eb;font-weight:500;'>" . e($inv->number) . '</a> &nbsp;' . $chip . $download
            );
        };

        $doc = $record->decontDocument;
        if ($doc) {
            $when = MarketplaceTz::fmt($doc->issued_at ?? $doc->created_at, 'd.m.Y', null);
            $icons = '';
            if ($doc->file_path && $doc->download_url) {
                $icons .= static::iconLink($doc->download_url, 'Descarcă decontul', static::ICON_DOWNLOAD);
            }
            if (! in_array($record->status, ['rejected', 'cancelled'], true)) {
                $icons .= static::iconWire("mountAction('regenerate_decont')", 'Regenerează decontul', static::ICON_REGENERATE);
            }
            $decont = static::uiLine('Decont PDF', static::uiChip('generat', '#059669'), 'din ' . e($when) . ($icons !== '' ? ' ' . $icons : ''));
        } else {
            $decont = static::uiLine('Decont PDF', $muted('negenerat'));
        }

        $rows = $decont;
        $onTop = ($record->commission_mode ?? 'included') === 'added_on_top';
        if ($record->invoice || $onTop) {
            $rows .= $invoiceRow('Factură comision (client general)', $record->invoice, 'neemisă');
        }
        if ($record->posInvoice || $record->invoicedCommission() > 0.005 || ! $onTop) {
            $rows .= $invoiceRow('Factură organizator', $record->posInvoice, $record->invoicedCommission() > 0.005 ? 'neemisă: comisionul nu se reține' : 'neemisă');
        }

        return static::uiWrap($rows);
    }

    /** Decont respins: cine, când și de ce. */
    public static function renderRejection(MarketplacePayout $record): \Illuminate\Support\HtmlString
    {
        $who = $record->rejectedByUser?->name;
        $when = $record->rejected_at ? MarketplaceTz::fmt($record->rejected_at, 'd.m.Y H:i', null) : null;
        $meta = e(implode(' · ', array_filter([$when, $who ? 'de ' . $who . ' (admin)' : null])));

        return new \Illuminate\Support\HtmlString(
            "<div style='padding:14px 16px;border-radius:10px;background:rgba(220,38,38,.07);border:1px solid rgba(220,38,38,.35);'>"
            . "<div style='font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#dc2626;'>Decont respins" . ($meta !== '' ? " <span style='text-transform:none;letter-spacing:0;font-weight:400;color:#9ca3af;'>· {$meta}</span>" : '') . '</div>'
            . "<div style='font-size:13px;margin-top:4px;white-space:pre-line;'>" . e((string) ($record->rejection_reason ?: 'fără motiv')) . '</div>'
            . '</div>'
        );
    }

    public const ICON_DOWNLOAD = 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3';

    public const ICON_REGENERATE = 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99';

    /** Iconiță galbenă care deschide un link (ex. descărcare). */
    protected static function iconLink(string $url, string $title, string $path): string
    {
        $color = static::ACTION_ICON_COLOR;

        return "<a href='" . e($url) . "' target='_blank' rel='noopener' title='" . e($title) . "' aria-label='" . e($title) . "'"
            . " style='display:inline-flex;vertical-align:-3px;margin-left:4px;color:{$color};'>" . static::iconSvg($path, 15) . '</a>';
    }

    /** Iconiță galbenă care pornește o acțiune a paginii (Livewire). */
    protected static function iconWire(string $call, string $title, string $path): string
    {
        $color = static::ACTION_ICON_COLOR;

        return "<button type='button' wire:click=\"{$call}\" title='" . e($title) . "' aria-label='" . e($title) . "'"
            . " style='display:inline-flex;vertical-align:-3px;margin-left:4px;color:{$color};cursor:pointer;'>" . static::iconSvg($path, 15) . '</button>';
    }

    /** Butoanele din colțul „Detalii bilete”: acțiuni ale paginii ViewPayout. */
    public static function ticketActionsHtml($livewire): ?\Illuminate\Support\HtmlString
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getAction')) {
            return null;
        }
        $html = '';
        foreach (['snapshotDriftBadge', 'recalcBreakdown', 'editTicketSelection'] as $name) {
            try {
                $action = $livewire->getAction($name, false);
            } catch (\Throwable $e) {
                $action = null;
            }
            if ($action && $action->isVisible()) {
                $html .= $action->size('sm')->toHtml();
            }
        }

        return $html === '' ? null : new \Illuminate\Support\HtmlString("<div style='display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;'>{$html}</div>");
    }
}
