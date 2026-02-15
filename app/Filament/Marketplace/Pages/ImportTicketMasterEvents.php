<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\AffiliateEventSource;
use App\Services\TicketMasterImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;

class ImportTicketMasterEvents extends Page implements HasForms
{
    use HasMarketplaceContext;
    use InteractsWithForms;

    protected static string $view = 'filament.marketplace.pages.import-ticketmaster-events';
    protected static ?string $title = 'Import TicketMaster';
    protected static ?string $navigationLabel = 'Import TicketMaster';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 11;

    // Form state
    public ?int $affiliate_event_source_id = null;
    public ?string $keyword = null;
    public ?string $countryCode = null;
    public ?string $city = null;
    public ?string $classificationName = null;
    public ?string $startDateTime = null;
    public ?string $endDateTime = null;
    public int $size = 50;

    // Results
    public ?array $importResult = null;
    public ?array $previewEvents = null;
    public bool $isPreview = false;

    public function form(Form $form): Form
    {
        $marketplace = static::getMarketplaceClient();

        return $form->schema([
            SC\Section::make('Configurare Import')
                ->description('Selectează sursa TicketMaster și configurează filtrele pentru import.')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Forms\Components\Select::make('affiliate_event_source_id')
                        ->label('Sursa de afiliere')
                        ->helperText('Selectează sursa care are API key TicketMaster configurat în setări.')
                        ->options(function () use ($marketplace) {
                            return AffiliateEventSource::query()
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->previewEvents = null;
                            $this->importResult = null;
                        }),

                    SC\Group::make()
                        ->visible(fn (SGet $get) => (bool) $get('affiliate_event_source_id'))
                        ->schema([
                            // Show API key status
                            Forms\Components\Placeholder::make('api_key_status')
                                ->label('Status API Key')
                                ->content(function (SGet $get) {
                                    $sourceId = $get('affiliate_event_source_id');
                                    if (!$sourceId) return '—';
                                    $source = AffiliateEventSource::find($sourceId);
                                    $key = $source?->settings['ticketmaster_api_key'] ?? null;
                                    if ($key) {
                                        $masked = substr($key, 0, 4) . '...' . substr($key, -4);
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="text-success-500 font-medium">Configurat</span> <span class="text-gray-400 text-xs">(' . e($masked) . ')</span>'
                                        );
                                    }
                                    return new \Illuminate\Support\HtmlString(
                                        '<span class="text-danger-500 font-medium">Lipsește!</span> '
                                        . '<span class="text-gray-400 text-xs">Editează sursa și adaugă ticketmaster_api_key în câmpul Settings (JSON).</span>'
                                    );
                                }),
                        ]),
                ]),

            SC\Section::make('Filtre Căutare')
                ->description('Filtrează evenimentele din TicketMaster pe care vrei să le imporți.')
                ->icon('heroicon-o-funnel')
                ->visible(fn (SGet $get) => (bool) $get('affiliate_event_source_id'))
                ->schema([
                    SC\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('keyword')
                            ->label('Cuvânt cheie')
                            ->placeholder('ex: concert, festival, Taylor Swift'),
                        Forms\Components\Select::make('countryCode')
                            ->label('Țară')
                            ->options(TicketMasterImportService::getCountryOptions())
                            ->searchable()
                            ->placeholder('Toate țările'),
                        Forms\Components\TextInput::make('city')
                            ->label('Oraș')
                            ->placeholder('ex: London, New York'),
                    ]),
                    SC\Grid::make(3)->schema([
                        Forms\Components\Select::make('classificationName')
                            ->label('Categorie')
                            ->options(TicketMasterImportService::getClassificationOptions())
                            ->placeholder('Toate categoriile'),
                        Forms\Components\DatePicker::make('startDateTime')
                            ->label('De la data')
                            ->native(false),
                        Forms\Components\DatePicker::make('endDateTime')
                            ->label('Până la data')
                            ->native(false),
                    ]),
                    Forms\Components\TextInput::make('size')
                        ->label('Număr maxim rezultate')
                        ->numeric()
                        ->default(50)
                        ->minValue(1)
                        ->maxValue(200),
                ]),
        ]);
    }

    /**
     * Preview events before importing
     */
    public function preview(): void
    {
        $this->importResult = null;

        $source = $this->getSelectedSource();
        if (!$source) return;

        try {
            $service = new TicketMasterImportService($source);
            $data = $service->searchEvents($this->buildSearchParams());

            $events = $data['_embedded']['events'] ?? [];
            $this->previewEvents = array_map(function ($e) {
                return [
                    'id' => $e['id'] ?? '',
                    'name' => $e['name'] ?? 'Unnamed',
                    'date' => $e['dates']['start']['localDate'] ?? '—',
                    'time' => $e['dates']['start']['localTime'] ?? '',
                    'venue' => $e['_embedded']['venues'][0]['name'] ?? '—',
                    'city' => $e['_embedded']['venues'][0]['city']['name'] ?? '—',
                    'country' => $e['_embedded']['venues'][0]['country']['countryCode'] ?? '',
                    'url' => $e['url'] ?? '',
                    'price_min' => $e['priceRanges'][0]['min'] ?? null,
                    'price_max' => $e['priceRanges'][0]['max'] ?? null,
                    'currency' => $e['priceRanges'][0]['currency'] ?? '',
                    'category' => $e['classifications'][0]['segment']['name'] ?? '—',
                    'image' => collect($e['images'] ?? [])->firstWhere('ratio', '16_9')['url']
                        ?? ($e['images'][0]['url'] ?? null),
                ];
            }, $events);

            $this->isPreview = true;
            $totalAvailable = $data['page']['totalElements'] ?? 0;

            Notification::make()
                ->title("Găsite {$totalAvailable} evenimente")
                ->body(count($this->previewEvents) . ' afișate. Verifică lista și apasă "Importă" pentru a le adăuga.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->previewEvents = null;
            Notification::make()
                ->title('Eroare la căutare')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Actually import the events
     */
    public function import(): void
    {
        $source = $this->getSelectedSource();
        if (!$source) return;

        try {
            $service = new TicketMasterImportService($source);
            $result = $service->importEvents($this->buildSearchParams());

            $this->importResult = $result;
            $this->previewEvents = null;
            $this->isPreview = false;

            $msg = "Importate: {$result['imported']}, Omise (duplicate): {$result['skipped']}";
            if (!empty($result['errors'])) {
                $msg .= ', Erori: ' . count($result['errors']);
            }

            Notification::make()
                ->title('Import finalizat')
                ->body($msg)
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Eroare la import')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getSelectedSource(): ?AffiliateEventSource
    {
        if (!$this->affiliate_event_source_id) {
            Notification::make()
                ->title('Selectează o sursă')
                ->warning()
                ->send();
            return null;
        }

        $source = AffiliateEventSource::find($this->affiliate_event_source_id);
        if (!$source) {
            Notification::make()
                ->title('Sursa nu a fost găsită')
                ->danger()
                ->send();
            return null;
        }

        $apiKey = $source->settings['ticketmaster_api_key'] ?? null;
        if (!$apiKey) {
            Notification::make()
                ->title('API Key lipsă')
                ->body('Editează sursa de afiliere și adaugă ticketmaster_api_key în câmpul Settings.')
                ->danger()
                ->send();
            return null;
        }

        return $source;
    }

    protected function buildSearchParams(): array
    {
        $params = [
            'size' => $this->size ?? 50,
        ];

        if ($this->keyword) $params['keyword'] = $this->keyword;
        if ($this->countryCode) $params['countryCode'] = $this->countryCode;
        if ($this->city) $params['city'] = $this->city;
        if ($this->classificationName) $params['classificationName'] = $this->classificationName;

        if ($this->startDateTime) {
            $params['startDateTime'] = \Carbon\Carbon::parse($this->startDateTime)->startOfDay()->format('Y-m-d\TH:i:s\Z');
        }
        if ($this->endDateTime) {
            $params['endDateTime'] = \Carbon\Carbon::parse($this->endDateTime)->endOfDay()->format('Y-m-d\TH:i:s\Z');
        }

        return $params;
    }
}
