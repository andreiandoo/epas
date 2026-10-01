<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\CouponCodeResource;
use App\Models\Coupon\CouponCampaign;
use App\Models\Coupon\CouponCodeBatch;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\TicketType;
use App\Services\Coupon\BulkCouponCodeGenerator;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Generare în masă de coduri de reducere: N coduri unice, de unică folosință,
 * pe un singur tip de bilet, descărcate ca CSV pentru organizator.
 */
class BulkCouponCodes extends Page implements HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use HasMarketplaceContext;
    use InteractsWithTable;

    protected static string $resource = CouponCodeResource::class;

    protected string $view = 'filament.marketplace.coupon-codes.bulk';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(CouponCodeBatch::enabled(), 404);

        $this->form->fill([
            'quantity' => 100,
            'code_length' => 8,
            'discount_type' => 'percentage',
            'first_purchase_only' => false,
            'combinable' => false,
        ]);
    }

    public function getTitle(): string
    {
        return 'Bulk coupon codes';
    }

    public function getBreadcrumbs(): array
    {
        return [
            CouponCodeResource::getUrl('index') => 'Coupon Codes',
            '#' => 'Bulk coupon codes',
        ];
    }

    public function form(Schema $form): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $form
            ->statePath('data')
            ->schema([
                SC\Section::make('Generare')
                    ->description('Codurile se generează automat: litere mari și cifre, fără caracterele care se confundă (0, O, 1, I, L). Fiecare cod e unic și se poate folosi o singură dată.')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Număr de coduri')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(BulkCouponCodeGenerator::MAX_QUANTITY)
                            ->helperText('Maximum ' . number_format(BulkCouponCodeGenerator::MAX_QUANTITY, 0, ',', '.') . ' pe lot.'),

                        Forms\Components\TextInput::make('code_length')
                            ->label('Caractere per cod')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(BulkCouponCodeGenerator::MIN_LENGTH)
                            ->maxValue(BulkCouponCodeGenerator::MAX_LENGTH)
                            ->live(onBlur: true)
                            ->helperText('Între ' . BulkCouponCodeGenerator::MIN_LENGTH . ' și ' . BulkCouponCodeGenerator::MAX_LENGTH . ', fără prefix.'),

                        Forms\Components\TextInput::make('prefix')
                            ->label('Prefix (opțional)')
                            ->maxLength(10)
                            ->regex('/^[A-Za-z0-9-]*$/')
                            ->validationMessages(['regex' => 'Doar litere, cifre și cratimă.'])
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => BulkCouponCodeGenerator::normalizePrefix($state))
                            ->placeholder('ex. QFEEL-'),

                        Forms\Components\Placeholder::make('preview')
                            ->label('Exemplu')
                            ->content(function (Get $get) {
                                $length = max(BulkCouponCodeGenerator::MIN_LENGTH, min(BulkCouponCodeGenerator::MAX_LENGTH, (int) ($get('code_length') ?: 8)));
                                $prefix = BulkCouponCodeGenerator::normalizePrefix($get('prefix')) ?? '';

                                return new HtmlString('<span class="font-mono text-base font-semibold tracking-wider">' . e($prefix . BulkCouponCodeGenerator::randomCode($length)) . '</span>');
                            }),
                    ])->columns(4),

                SC\Section::make('Eveniment și bilet')
                    ->description('Codurile se aplică unui singur tip de bilet.')
                    ->extraAttributes(['style' => 'overflow: visible;'])
                    ->schema([
                        Forms\Components\Select::make('campaign_id')
                            ->label('Campanie')
                            ->options(fn () => CouponCampaign::where('marketplace_client_id', $marketplace?->id)->get()
                                ->mapWithKeys(fn ($c) => [$c->id => is_array($c->name) ? ($c->name['ro'] ?? $c->name['en'] ?? (collect($c->name)->first() ?: 'Fără nume')) : ($c->name ?: 'Fără nume')]))
                            ->searchable(),

                        Forms\Components\Select::make('marketplace_organizer_id')
                            ->label('Organizator')
                            ->options(fn () => MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('event_id', null);
                                $set('ticket_type_id', null);
                            })
                            ->helperText('Filtrează evenimentele.'),

                        Forms\Components\Select::make('event_id')
                            ->label('Eveniment')
                            ->required()
                            ->searchable()
                            ->options(function (Get $get) use ($marketplace) {
                                return Event::where('marketplace_client_id', $marketplace?->id)
                                    ->when($get('marketplace_organizer_id'), fn ($q, $org) => $q->where('marketplace_organizer_id', $org))
                                    ->orderByDesc('event_date')
                                    ->limit(500)
                                    ->get()
                                    ->mapWithKeys(function ($e) {
                                        $title = is_array($e->title) ? ($e->title['ro'] ?? $e->title['en'] ?? (collect($e->title)->first() ?: '')) : $e->title;

                                        return [$e->id => trim(($title ?: ('Eveniment #' . $e->id)) . ($e->event_date ? ' — ' . $e->event_date->format('d.m.Y') : ''))];
                                    });
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('ticket_type_id', null);
                                if ($state && ($org = Event::whereKey($state)->value('marketplace_organizer_id'))) {
                                    $set('marketplace_organizer_id', $org);
                                }
                            }),

                        Forms\Components\Select::make('ticket_type_id')
                            ->label('Tip bilet')
                            ->required()
                            ->options(function (Get $get) {
                                if (!$get('event_id')) {
                                    return [];
                                }

                                return TicketType::where('event_id', $get('event_id'))
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->reject(fn ($tt) => $tt->isTestPos() || ($tt->meta['is_invitation'] ?? false))
                                    ->mapWithKeys(fn ($tt) => [$tt->id => (is_array($tt->name) ? ($tt->name['ro'] ?? (collect($tt->name)->first() ?: '')) : $tt->name)
                                        . ' — ' . number_format(($tt->price_cents ?? 0) / 100, 2, ',', '.') . ' lei'
                                        . ($tt->status !== 'active' ? ' (inactiv)' : '')]);
                            })
                            ->disabled(fn (Get $get) => !$get('event_id'))
                            ->helperText(fn (Get $get) => $get('event_id') ? null : 'Alege întâi evenimentul.'),
                    ])->columns(4),

                SC\Section::make('Reducere')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label('Tip reducere')
                            ->options([
                                'percentage' => 'Procent',
                                'fixed_amount' => 'Sumă fixă',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label('Valoare')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get) => $get('discount_type') === 'percentage' ? 100 : null)
                            ->suffix(fn (Get $get) => $get('discount_type') === 'percentage' ? '%' : 'lei'),

                        Forms\Components\TextInput::make('max_discount_amount')
                            ->label('Reducere maximă')
                            ->numeric()
                            ->suffix('lei')
                            ->visible(fn (Get $get) => $get('discount_type') === 'percentage')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Plafon pentru reducerea procentuală'),

                        Forms\Components\TextInput::make('min_purchase_amount')
                            ->label('Valoare minimă comandă')
                            ->numeric()
                            ->suffix('lei'),
                    ])->columns(4),

                SC\Section::make('Valabilitate și opțiuni')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Valabil de la')
                            ->seconds(false),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiră la')
                            ->seconds(false)
                            ->after('starts_at'),

                        Forms\Components\Toggle::make('first_purchase_only')
                            ->label('Doar la prima comandă'),

                        Forms\Components\Toggle::make('combinable')
                            ->label('Combinabil cu alte coduri'),

                        Forms\Components\Placeholder::make('usage_info')
                            ->label('Utilizări')
                            ->content('1 utilizare per cod, 1 per client. Codurile nu sunt publice și nu apar în contul organizatorului — le primește prin CSV.')
                            ->columnSpanFull(),
                    ])->columns(4),
            ]);
    }

    /** Generează lotul și pornește descărcarea CSV-ului. */
    public function generate()
    {
        $data = $this->form->getState();
        $marketplace = static::getMarketplaceClient();

        try {
            $batch = app(BulkCouponCodeGenerator::class)->generate(
                $data,
                (int) $marketplace->id,
                Auth::guard('marketplace_admin')->id(),
                Auth::id(),
            );
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Generarea a eșuat')->body($e->getMessage())->send();

            return null;
        }

        Notification::make()->success()
            ->title('Coduri generate')
            ->body(number_format($batch->quantity, 0, ',', '.') . ' coduri în ' . $batch->label . '. Descărcarea CSV pornește automat; îl găsești oricând în lista de loturi de mai jos.')
            ->send();

        $this->resetTable();

        return app(BulkCouponCodeGenerator::class)->csv($batch);
    }

    public function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->heading('Loturi generate')
            ->query(
                CouponCodeBatch::query()
                    ->where('marketplace_client_id', $marketplace?->id)
                    ->with(['organizer:id,name', 'event:id,title,event_date', 'ticketType:id,name'])
                    ->withCount([
                        'codes',
                        'codes as used_count' => fn ($q) => $q->where('current_uses', '>', 0),
                        'codes as active_count' => fn ($q) => $q->where('status', 'active'),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Lot')
                    ->weight('bold')
                    ->description(fn ($record) => $record->prefix ? 'Prefix: ' . $record->prefix : null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizator')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('event_title')
                    ->label('Eveniment / bilet')
                    ->state(function ($record) {
                        $e = $record->event;
                        $title = $e ? (is_array($e->title) ? ($e->title['ro'] ?? $e->title['en'] ?? (collect($e->title)->first() ?: '')) : $e->title) : '—';

                        return $title;
                    })
                    ->description(fn ($record) => $record->ticketType
                        ? (is_array($record->ticketType->name) ? ($record->ticketType->name['ro'] ?? (collect($record->ticketType->name)->first() ?: '')) : $record->ticketType->name)
                        : null)
                    ->limit(40),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Reducere')
                    ->state(function ($record) {
                        $s = $record->settings ?? [];
                        $v = (float) ($s['discount_value'] ?? 0);

                        return ($s['discount_type'] ?? '') === 'percentage'
                            ? rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%'
                            : number_format($v, 2, ',', '.') . ' lei';
                    }),
                Tables\Columns\TextColumn::make('codes_count')
                    ->label('Coduri')
                    ->numeric(locale: 'ro'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Folosite')
                    ->numeric(locale: 'ro'),
                Tables\Columns\TextColumn::make('active_count')
                    ->label('Active')
                    ->numeric(locale: 'ro'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descarcă CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (CouponCodeBatch $record) => app(BulkCouponCodeGenerator::class)->csv($record)),
                Action::make('codes')
                    ->label('Vezi codurile')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn (CouponCodeBatch $record) => CouponCodeResource::getUrl('index', ['filters' => ['batch_id' => ['value' => $record->id]]])),
                Action::make('deactivate')
                    ->label('Dezactivează lotul')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Codurile încă nefolosite din lot devin inactive și nu mai pot fi aplicate la checkout. Codurile deja folosite rămân neschimbate.')
                    ->visible(fn (CouponCodeBatch $record) => $record->active_count > 0)
                    ->action(function (CouponCodeBatch $record) {
                        $n = $record->codes()->where('status', 'active')->update(['status' => 'disabled', 'updated_at' => now()]);
                        app(BulkCouponCodeGenerator::class)->syncSeries($record->event);
                        Notification::make()->success()->title($n . ' coduri dezactivate')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Niciun lot generat încă');
    }
}
