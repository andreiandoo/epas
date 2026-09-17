<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\PartnerAdResource\Pages;
use App\Models\Event;
use App\Models\MarketplacePartner;
use App\Models\PartnerAd;
use App\Models\PartnerAdFormat;
use App\Models\PartnerEventFeedItem;
use App\Services\Partners\PartnerAds;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ads published in a media partner's ad zones (microservice media-partners).
 */
class PartnerAdResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = PartnerAd::class;
    protected static ?string $slug = 'partner-ads';
    protected static ?string $navigationLabel = 'Reclame parteneri';
    protected static ?string $modelLabel = 'reclamă partener';
    protected static ?string $pluralModelLabel = 'reclame parteneri';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice(MarketplacePartner::MICROSERVICE);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', static::getMarketplaceClientId())
            ->with('partner')
            ->withSum('stats', 'impressions')
            ->withSum('stats', 'clicks');
    }

    /**
     * Partners of this marketplace that take ads.
     */
    public static function adPartners(): Collection
    {
        return MarketplacePartner::where('marketplace_client_id', static::getMarketplaceClientId())
            ->where('status', 'active')
            ->whereNotNull('ads_api_url')
            ->orderBy('name')
            ->get();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plasare')->schema([
                Forms\Components\Select::make('marketplace_partner_id')
                    ->label('Partener')
                    ->options(fn () => static::adPartners()->pluck('name', 'id')->all())
                    ->default(fn () => static::adPartners()->count() === 1 ? static::adPartners()->first()->id : null)
                    ->required()
                    ->native(false)
                    ->live()
                    // Fixed once saved: moving an ad would leave it live on the first partner.
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Partenerul nu se mai poate schimba; creează o reclamă nouă.' : null)
                    ->afterStateUpdated(fn (Set $set) => $set('format', null)),

                // Value is "zone|format": format codes repeat across zones.
                Forms\Components\Select::make('format')
                    ->label('Model (zonă și dimensiune)')
                    ->options(function (Get $get) {
                        $current = PartnerAdFormat::splitKey($get('format'));

                        return PartnerAdFormat::where('marketplace_partner_id', $get('marketplace_partner_id'))
                            ->where(fn ($q) => $q
                                ->where('is_available', true)
                                ->when($current, fn ($w) => $w->orWhere(fn ($c) => $c
                                    ->where('slot', $current[0])
                                    ->where('format', $current[1]))))
                            ->orderBy('slot')
                            ->orderBy('format')
                            ->get()
                            ->mapWithKeys(fn (PartnerAdFormat $format) => [$format->key() => $format->optionLabel()])
                            ->all();
                    })
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Modelele vin de la partener. Dacă lista e goală, apasă „Actualizează zonele” în lista de reclame.'),

                Forms\Components\Placeholder::make('format_requirements')
                    ->label('Cerințe model')
                    ->content(fn (Get $get) => static::formatSummary(static::formatFor($get)))
                    ->visible(fn (Get $get) => filled($get('format')))
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Conținut')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nume intern')
                    ->required()
                    ->maxLength(191)
                    ->helperText('Doar pentru listă; devine utm_campaign dacă nu setezi alta.')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('image_path')
                    ->label('Imagine')
                    ->image()
                    ->disk('public')
                    ->directory('partner-ads')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->visible(fn (Get $get) => static::formatFor($get)?->usesField('image') ?? true)
                    ->required(fn (Get $get) => (bool) (static::formatFor($get)?->imageSize('image')['required'] ?? false))
                    ->helperText(fn (Get $get) => static::sizeHint(static::formatFor($get)?->imageSize('image'))),

                Forms\Components\FileUpload::make('image_mobile_path')
                    ->label('Imagine mobil')
                    ->image()
                    ->disk('public')
                    ->directory('partner-ads')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->visible(fn (Get $get) => static::formatFor($get)?->imageSize('image_mobile') !== null)
                    ->required(fn (Get $get) => (bool) (static::formatFor($get)?->imageSize('image_mobile')['required'] ?? false))
                    ->helperText(fn (Get $get) => static::sizeHint(static::formatFor($get)?->imageSize('image_mobile'))),

                Forms\Components\TextInput::make('title')
                    ->label('Titlu')
                    ->visible(fn (Get $get) => static::formatFor($get)?->usesField('title') ?? true)
                    ->maxLength(fn (Get $get) => static::formatFor($get)?->maxLength('title') ?? 191),

                Forms\Components\TextInput::make('cta')
                    ->label('Text buton')
                    ->placeholder('ex. Ia bilete')
                    ->visible(fn (Get $get) => static::formatFor($get)?->usesField('cta') ?? true)
                    ->maxLength(fn (Get $get) => static::formatFor($get)?->maxLength('cta') ?? 64),

                Forms\Components\Textarea::make('text')
                    ->label('Subtitlu')
                    ->rows(2)
                    ->visible(fn (Get $get) => static::formatFor($get)?->usesField('text') ?? true)
                    ->maxLength(fn (Get $get) => static::formatFor($get)?->maxLength('text') ?? 500)
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Link')->schema([
                Forms\Components\Radio::make('link_type')
                    ->label('Duce către')
                    ->options(['event' => 'Un eveniment', 'url' => 'Alt link'])
                    ->default('event')
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\Select::make('event_id')
                    ->label('Eveniment')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::searchEvents($search))
                    ->getOptionLabelUsing(fn ($value) => static::ownEventLabel((int) $value))
                    ->visible(fn (Get $get) => $get('link_type') === 'event')
                    ->required(fn (Get $get) => $get('link_type') === 'event')
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $row = $state ? static::feedRow((int) $state) : null;
                        if ($row?->listed_until && blank($get('ends_at'))) {
                            // UTC: the picker converts to the marketplace timezone itself.
                            $set('ends_at', $row->listed_until->copy()->utc()->toDateTimeString());
                        }
                    })
                    ->hintAction(
                        Action::make('fillFromEvent')
                            ->label('Completează din eveniment')
                            ->icon('heroicon-m-sparkles')
                            ->visible(fn (Get $get) => filled($get('event_id')))
                            ->action(fn (Get $get, Set $set) => static::fillFromEvent($get, $set))
                    )
                    ->helperText('Linkul spre pagina evenimentului, cu parametri UTM, se generează automat.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('custom_url')
                    ->label('Link')
                    ->url()
                    ->maxLength(2048)
                    ->visible(fn (Get $get) => $get('link_type') === 'url')
                    ->required(fn (Get $get) => $get('link_type') === 'url')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('utm_campaign')
                    ->label('utm_campaign')
                    ->placeholder('implicit: numele reclamei')
                    ->maxLength(100),
            ])->columns(2),

            Section::make('Perioadă și stare')->schema([
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('De la')
                    ->native(false)
                    ->seconds(false)
                    ->timezone(static::timezone()),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Până la')
                    ->native(false)
                    ->seconds(false)
                    ->timezone(static::timezone())
                    ->helperText('La un eveniment, implicit sfârșitul evenimentului.'),

                Forms\Components\TextInput::make('priority')
                    ->label('Prioritate')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Stare')
                    ->options([
                        PartnerAd::STATUS_ACTIVE => 'Activă (se publică)',
                        PartnerAd::STATUS_PAUSED => 'Pe pauză (retrasă)',
                        PartnerAd::STATUS_DRAFT => 'Ciornă (nu se trimite)',
                    ])
                    ->default(PartnerAd::STATUS_ACTIVE)
                    ->required()
                    ->native(false),

                Forms\Components\Placeholder::make('paused_reason_info')
                    ->label('Pusă pe pauză automat')
                    ->content(fn (?PartnerAd $record) => $record?->paused_reason)
                    ->visible(fn (?PartnerAd $record) => filled($record?->paused_reason))
                    ->columnSpanFull(),
            ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Reclamă')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (PartnerAd $record) => ($record->partner?->name ?? '—') . ' · ' . $record->format),
                Tables\Columns\TextColumn::make('link')
                    ->label('Link')
                    ->state(fn (PartnerAd $record) => $record->link_type === 'event'
                        ? static::eventLabel((int) $record->event_id)
                        : $record->custom_url)
                    ->limit(45)
                    ->wrap(),
                Tables\Columns\TextColumn::make('period')
                    ->label('Perioadă')
                    ->state(fn (PartnerAd $record) => static::periodLabel($record)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        PartnerAd::STATUS_ACTIVE => 'Activă',
                        PartnerAd::STATUS_PAUSED => 'Pe pauză',
                        default => 'Ciornă',
                    })
                    ->color(fn (string $state) => match ($state) {
                        PartnerAd::STATUS_ACTIVE => 'success',
                        PartnerAd::STATUS_PAUSED => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (PartnerAd $record) => $record->paused_reason),
                Tables\Columns\TextColumn::make('sync_status')
                    ->label('Pe site-ul partenerului')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        PartnerAd::SYNC_PENDING => 'Se trimite',
                        PartnerAd::SYNC_PUBLISHED => 'Publicată',
                        PartnerAd::SYNC_WITHDRAWN => 'Retrasă',
                        PartnerAd::SYNC_FAILED => 'Eroare',
                        default => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        PartnerAd::SYNC_PUBLISHED => 'success',
                        PartnerAd::SYNC_FAILED => 'danger',
                        PartnerAd::SYNC_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (PartnerAd $record) => $record->sync_error),
                Tables\Columns\TextColumn::make('stats_sum_impressions')
                    ->label('Afișări')
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stats_sum_clicks')
                    ->label('Click-uri')
                    ->numeric()
                    ->placeholder('—')
                    ->description(fn (PartnerAd $record) => $record->stats_sum_impressions > 0
                        ? 'CTR ' . number_format($record->stats_sum_clicks / $record->stats_sum_impressions * 100, 2, ',', '.') . '%'
                        : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stare')
                    ->options([
                        PartnerAd::STATUS_ACTIVE => 'Activă',
                        PartnerAd::STATUS_PAUSED => 'Pe pauză',
                        PartnerAd::STATUS_DRAFT => 'Ciornă',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                Action::make('pause')
                    ->label('Pauză')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (PartnerAd $record) => $record->status === PartnerAd::STATUS_ACTIVE)
                    ->action(fn (PartnerAd $record) => static::changeStatus($record, PartnerAd::STATUS_PAUSED)),
                Action::make('activate')
                    ->label('Activează')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (PartnerAd $record) => $record->status !== PartnerAd::STATUS_ACTIVE)
                    ->action(fn (PartnerAd $record) => static::changeStatus($record, PartnerAd::STATUS_ACTIVE)),
                Action::make('resend')
                    ->label('Retrimite')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PartnerAd $record) => $record->sync_status === PartnerAd::SYNC_FAILED)
                    ->action(function (PartnerAd $record) {
                        app(PartnerAds::class)->sync($record);
                        Notification::make()->title('Reclama a fost pusă din nou la trimis')->success()->send();
                    }),
                \Filament\Actions\DeleteAction::make()
                    ->modalDescription('Reclama se retrage și de pe site-ul partenerului.')
                    ->before(fn (PartnerAd $record) => app(PartnerAds::class)->withdrawBeforeDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerAds::route('/'),
            'create' => Pages\CreatePartnerAd::route('/create'),
            'edit' => Pages\EditPartnerAd::route('/{record}/edit'),
        ];
    }

    public static function changeStatus(PartnerAd $ad, string $status): void
    {
        $ad->forceFill(['status' => $status, 'paused_reason' => null])->save();
        app(PartnerAds::class)->sync($ad);

        Notification::make()
            ->title($status === PartnerAd::STATUS_ACTIVE ? 'Reclama se publică' : 'Reclama se retrage de pe site-ul partenerului')
            ->success()
            ->send();
    }

    /**
     * Shared by the create and edit pages: fills the zone from the format and
     * checks image sizes against the partner's format.
     *
     * @throws ValidationException
     */
    public static function prepareForSave(array $data): array
    {
        $key = PartnerAdFormat::splitKey($data['format'] ?? null);
        $format = $key
            ? PartnerAdFormat::where('marketplace_partner_id', $data['marketplace_partner_id'] ?? null)
                ->where('slot', $key[0])
                ->where('format', $key[1])
                ->first()
            : null;

        if (!$format) {
            throw ValidationException::withMessages(['data.format' => 'Alege un model oferit de partener.']);
        }

        $data['slot'] = $format->slot;
        $data['format'] = $format->format;

        // The event must belong to this marketplace, whatever the browser sent.
        if (($data['link_type'] ?? 'event') === 'event'
            && !Event::whereKey($data['event_id'] ?? 0)->where('marketplace_client_id', static::getMarketplaceClientId())->exists()) {
            throw ValidationException::withMessages(['data.event_id' => 'Alege un eveniment din acest marketplace.']);
        }

        foreach (['image_path' => 'image', 'image_mobile_path' => 'image_mobile'] as $field => $key) {
            $problem = static::imageProblem($data[$field] ?? null, $format->imageSize($key));
            if ($problem) {
                throw ValidationException::withMessages(["data.{$field}" => $problem]);
            }
        }

        if (($data['link_type'] ?? 'event') === 'event') {
            $data['custom_url'] = null;
        } else {
            $data['event_id'] = null;
        }

        if (($data['status'] ?? null) !== PartnerAd::STATUS_PAUSED) {
            $data['paused_reason'] = null;
        }

        return $data;
    }

    /**
     * The same aspect ratio as the format, at least its size (so 2× images for sharp screens pass).
     */
    private static function imageProblem(?string $path, ?array $size): ?string
    {
        if (!$path || !$size) {
            return null;
        }

        try {
            $dimensions = @getimagesize(Storage::disk('public')->path($path));
        } catch (\Throwable) {
            $dimensions = false;
        }

        if (!$dimensions) {
            return null;
        }

        [$width, $height] = $dimensions;
        $ratioOff = abs($width / max(1, $height) - $size['width'] / max(1, $size['height'])) > 0.01;

        if ($ratioOff || $width < $size['width'] || $height < $size['height']) {
            return "Imaginea trebuie să fie {$size['width']}×{$size['height']} px (sau mai mare, cu același raport). Ai încărcat {$width}×{$height} px.";
        }

        return null;
    }

    private static function formatFor(Get $get): ?PartnerAdFormat
    {
        $key = PartnerAdFormat::splitKey($get('format'));

        if (blank($get('marketplace_partner_id')) || !$key) {
            return null;
        }

        return PartnerAdFormat::where('marketplace_partner_id', $get('marketplace_partner_id'))
            ->where('slot', $key[0])
            ->where('format', $key[1])
            ->first();
    }

    private static function formatSummary(?PartnerAdFormat $format): string
    {
        if (!$format) {
            return '—';
        }

        $labels = ['image' => 'imagine', 'title' => 'titlu', 'text' => 'subtitlu', 'cta' => 'buton'];
        $parts = ['Câmpuri: ' . implode(', ', array_map(fn ($field) => $labels[$field] ?? $field, $format->fields()))];

        foreach (['image' => 'Imagine', 'image_mobile' => 'Imagine mobil'] as $key => $label) {
            $size = $format->imageSize($key);
            if ($size) {
                $parts[] = "{$label}: {$size['width']}×{$size['height']} px" . ($size['required'] ? ' (obligatorie)' : '');
            }
        }

        foreach (['title' => 'titlu', 'text' => 'subtitlu', 'cta' => 'buton'] as $field => $label) {
            if ($format->maxLength($field)) {
                $parts[] = "max. {$format->maxLength($field)} caractere la {$label}";
            }
        }

        return implode(' · ', $parts);
    }

    private static function sizeHint(?array $size): ?string
    {
        return $size ? "{$size['width']}×{$size['height']} px, sau mai mare cu același raport. Maximum 5 MB." : null;
    }

    private static function timezone(): string
    {
        return MarketplaceTz::tz(static::getMarketplaceClient());
    }

    private static function feedRow(int $eventId): ?PartnerEventFeedItem
    {
        return PartnerEventFeedItem::where('marketplace_client_id', static::getMarketplaceClientId())
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * Upcoming, not cancelled events, searched by title (accents ignored).
     */
    private static function searchEvents(string $search): array
    {
        return PartnerEventFeedItem::where('marketplace_client_id', static::getMarketplaceClientId())
            ->whereNull('removed_at')
            ->where('status', '!=', 'cancelled')
            ->where('listed_until', '>=', now())
            ->whereRaw("LOWER(unaccent(payload->>'title')) LIKE LOWER(unaccent(?))", ['%' . $search . '%'])
            ->orderBy('starts_at')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (PartnerEventFeedItem $row) => [$row->event_id => static::rowLabel($row)])
            ->all();
    }

    /**
     * Label for the event select; null (so the value is rejected) for events outside this marketplace.
     */
    private static function ownEventLabel(int $eventId): ?string
    {
        $row = $eventId ? static::feedRow($eventId) : null;

        if ($row) {
            return static::rowLabel($row);
        }

        $exists = $eventId && Event::whereKey($eventId)
            ->where('marketplace_client_id', static::getMarketplaceClientId())
            ->exists();

        return $exists ? static::eventLabel($eventId) : null;
    }

    private static function eventLabel(int $eventId): string
    {
        if (!$eventId) {
            return '—';
        }

        $row = static::feedRow($eventId);
        if ($row) {
            return static::rowLabel($row);
        }

        $title = Event::whereKey($eventId)
            ->where('marketplace_client_id', static::getMarketplaceClientId())
            ->value('title');
        $title = is_string($title) ? json_decode($title, true) ?? $title : $title;

        return is_array($title) ? ($title['ro'] ?? reset($title) ?: "#{$eventId}") : ($title ?: "Eveniment #{$eventId} (șters)");
    }

    private static function rowLabel(PartnerEventFeedItem $row): string
    {
        $payload = $row->payload ?? [];
        $date = !empty($payload['starts_at']) ? Carbon::parse($payload['starts_at'])->format('d.m.Y H:i') : '';
        $venue = trim(($payload['venue']['name'] ?? '') . ', ' . ($payload['venue']['city'] ?? ''), ', ');

        return implode(' — ', array_filter([$payload['title'] ?? "#{$row->event_id}", $date, $venue]));
    }

    private static function fillFromEvent(Get $get, Set $set): void
    {
        $row = static::feedRow((int) $get('event_id'));
        if (!$row) {
            return;
        }

        $payload = $row->payload ?? [];
        $format = static::formatFor($get);
        $title = (string) ($payload['title'] ?? '');

        $date = !empty($payload['starts_at'])
            ? Carbon::parse($payload['starts_at'])->locale('ro')->translatedFormat('j F Y')
            : '';
        $venue = trim(($payload['venue']['name'] ?? '') . ', ' . ($payload['venue']['city'] ?? ''), ', ');
        $text = implode(' · ', array_filter([$date, $venue]));

        $set('title', Str::limit($title, ($format?->maxLength('title') ?? 191) - 1, '…'));
        $set('text', Str::limit($text, ($format?->maxLength('text') ?? 500) - 1, '…'));
        if (blank($get('cta'))) {
            $set('cta', 'Ia bilete');
        }
        if (blank($get('name'))) {
            $set('name', Str::limit($title, 180, ''));
        }
        if ($row->listed_until && blank($get('ends_at'))) {
            // UTC: the picker converts to the marketplace timezone itself.
            $set('ends_at', $row->listed_until->copy()->utc()->toDateTimeString());
        }
    }

    private static function periodLabel(PartnerAd $ad): string
    {
        $timezone = static::timezone();
        $from = $ad->starts_at?->copy()->setTimezone($timezone)->format('d.m.Y');
        $to = $ad->ends_at?->copy()->setTimezone($timezone)->format('d.m.Y');

        return match (true) {
            $from && $to => "{$from} – {$to}",
            (bool) $from => "din {$from}",
            (bool) $to => "până la {$to}",
            default => 'fără limită',
        };
    }
}
