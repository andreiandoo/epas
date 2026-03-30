<?php

namespace App\Filament\Concerns;

use App\Models\Artist;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\Venue;
use App\Services\EventImport\DTOs\ImportedRow;
use App\Services\EventImport\EventImportService;
use App\Services\EventImport\Parsers\IabiletParser;
use App\Services\EventImport\Parsers\ImportParserInterface;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Livewire\WithFileUploads;

trait HasEventImport
{
    use WithFileUploads;

    // Stage tracking
    public int $stage = 1;

    // Stage 1: Event setup form
    public ?array $eventFormData = [];

    // Stage 2: File upload & preview
    public $uploadedFile = null;
    public ?string $storedFilePath = null;
    public array $csvHeaders = [];
    public array $csvPreview = [];
    public int $csvTotalRows = 0;
    public array $discoveredTicketTypes = [];

    // Stage 3: Processing
    public bool $isProcessing = false;
    public int $processedOrders = 0;
    public int $totalOrders = 0;
    public ?string $processingStatus = null;

    // Stage 4: Results
    public ?array $importResults = null;

    /**
     * Available import parsers.
     * @return ImportParserInterface[]
     */
    protected function getParsers(): array
    {
        return [
            'iabilet' => new IabiletParser(),
        ];
    }

    protected function getSourceOptions(): array
    {
        $options = [];
        foreach ($this->getParsers() as $key => $parser) {
            $options[$key] = $parser->sourceLabel();
        }
        return $options;
    }

    /**
     * Get tenant_id for the current context.
     * Override in panel-specific pages.
     */
    protected function resolveImportTenantId(): ?int
    {
        return null;
    }

    /**
     * Override in panel-specific pages to prepend extra fields (e.g. tenant selector).
     */
    protected function getExtraEventFormFields(): array
    {
        return [];
    }

    public function eventSetupForm(Schema $schema): Schema
    {
        $fields = $this->getExtraEventFormFields();

        return $schema
            ->statePath('eventFormData')
            ->schema(array_merge($fields, [
                SC\Grid::make(2)->schema([
                    Forms\Components\Select::make('import_source')
                        ->label('Sursă import')
                        ->options($this->getSourceOptions())
                        ->required()
                        ->default('iabilet')
                        ->native(false),

                    Forms\Components\Select::make('event_status')
                        ->label('Status eveniment')
                        ->options([
                            'completed' => 'Încheiat',
                            'ongoing' => 'În derulare',
                        ])
                        ->required()
                        ->default('completed')
                        ->native(false),
                ]),

                SC\Section::make('Detalii eveniment')->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Nume eveniment')
                        ->required()
                        ->maxLength(255),

                    SC\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Data eveniment')
                            ->required()
                            ->native(false),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Ora start')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Ora final')
                            ->seconds(false)
                            ->native(true),
                    ]),

                    Forms\Components\RichEditor::make('description')
                        ->label('Descriere')
                        ->columnSpanFull(),
                ]),

                SC\Section::make('Locație și artiști')->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Venue')
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return Venue::query()
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro') ?: $v->name) . ($v->city ? " ({$v->city})" : ''),
                                ]);
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return Venue::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%")
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro') ?: $v->name) . ($v->city ? " ({$v->city})" : ''),
                                ]);
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nume venue')
                                ->required(),
                            Forms\Components\TextInput::make('city')
                                ->label('Oraș'),
                            Forms\Components\TextInput::make('country')
                                ->label('Țară')
                                ->default('RO'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $venue = Venue::create([
                                'name' => ['ro' => $data['name']],
                                'slug' => \Illuminate\Support\Str::slug($data['name']),
                                'city' => $data['city'] ?? null,
                                'country' => $data['country'] ?? 'RO',
                                'tenant_id' => $this->resolveImportTenantId(),
                            ]);
                            return $venue->id;
                        }),

                    Forms\Components\Select::make('artist_ids')
                        ->label('Artiști')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return Artist::query()
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id');
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return Artist::where('name', 'like', "%{$search}%")
                                ->limit(20)
                                ->pluck('name', 'id');
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nume artist')
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $artist = Artist::create([
                                'name' => $data['name'],
                                'slug' => \Illuminate\Support\Str::slug($data['name']),
                                'letter' => strtoupper(substr($data['name'], 0, 1)),
                            ]);
                            return $artist->id;
                        }),
                ]),

                SC\Section::make('Taxonomii')->schema([
                    Forms\Components\Select::make('event_type_ids')
                        ->label('Tip eveniment')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return EventType::all()->mapWithKeys(fn ($t) => [
                                $t->id => $t->getTranslation('name', 'en') ?: $t->getTranslation('name', 'ro'),
                            ]);
                        })
                        ->live(),

                    Forms\Components\Select::make('event_genre_ids')
                        ->label('Gen eveniment')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $typeIds = $get('event_type_ids') ?? [];
                            if (empty($typeIds)) {
                                return EventGenre::all()->mapWithKeys(fn ($g) => [
                                    $g->id => $g->getTranslation('name', 'en') ?: $g->getTranslation('name', 'ro'),
                                ]);
                            }
                            return EventGenre::query()
                                ->whereHas('allowedEventTypes', fn ($q) => $q->whereIn('event_types.id', $typeIds))
                                ->get()
                                ->mapWithKeys(fn ($g) => [
                                    $g->id => $g->getTranslation('name', 'en') ?: $g->getTranslation('name', 'ro'),
                                ]);
                        }),
                ]),

                SC\Section::make('Comision sursă externă')->schema([
                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Rata comision (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\Select::make('commission_mode')
                            ->label('Mod comision')
                            ->options([
                                'included' => 'Inclus în prețul biletului',
                                'added_on_top' => 'Adăugat peste prețul biletului',
                            ])
                            ->default('included')
                            ->native(false),
                    ]),
                ]),
            ]));
    }

    public function goToStage2(): void
    {
        $data = $this->eventFormData;

        // Validate required fields
        if (empty($data['title'])) {
            Notification::make()->title('Completează numele evenimentului')->danger()->send();
            return;
        }
        if (empty($data['import_source'])) {
            Notification::make()->title('Selectează sursa de import')->danger()->send();
            return;
        }
        if (empty($data['event_date'])) {
            Notification::make()->title('Selectează data evenimentului')->danger()->send();
            return;
        }

        $this->stage = 2;
    }

    public function goBackToStage1(): void
    {
        $this->stage = 1;
    }

    public function uploadAndPreview(): void
    {
        if (!$this->uploadedFile) {
            Notification::make()->title('Încarcă un fișier CSV sau TSV')->danger()->send();
            return;
        }

        // Store the uploaded file
        $path = $this->uploadedFile->store('imports', 'local');
        $this->storedFilePath = storage_path('app/' . $path);

        $source = $this->eventFormData['import_source'] ?? 'iabilet';
        $parser = $this->getParsers()[$source] ?? null;

        if (!$parser) {
            Notification::make()->title('Parser necunoscut pentru sursa selectată')->danger()->send();
            return;
        }

        try {
            $allRows = $parser->parse($this->storedFilePath);
        } catch (\Throwable $e) {
            Notification::make()->title('Eroare la parsarea fișierului: ' . $e->getMessage())->danger()->send();
            return;
        }

        if (empty($allRows)) {
            Notification::make()->title('Fișierul nu conține date valide')->danger()->send();
            return;
        }

        $this->csvTotalRows = count($allRows);

        // Preview first 10 rows
        $this->csvPreview = array_map(function (ImportedRow $row) {
            return [
                'order_id' => $row->orderId,
                'order_date' => $row->orderDate,
                'client_name' => $row->clientName,
                'email' => $row->email,
                'ticket_type' => $row->ticketTypeName,
                'seat' => $row->seatLabel,
                'price' => $row->ticketPrice,
                'barcode' => $row->barcode,
                'order_status' => $row->orderStatus,
            ];
        }, array_slice($allRows, 0, 10));

        // Discover ticket types
        $types = [];
        foreach ($allRows as $row) {
            $name = $row->ticketTypeName ?? 'General';
            if (!isset($types[$name])) {
                $types[$name] = ['count' => 0, 'price' => $row->ticketPrice ?? 0];
            }
            $types[$name]['count']++;
        }
        $this->discoveredTicketTypes = $types;

        // Count unique orders
        $orderIds = [];
        foreach ($allRows as $row) {
            if ($row->orderId) {
                $orderIds[$row->orderId] = true;
            }
        }
        $this->totalOrders = count($orderIds) ?: $this->csvTotalRows;

        // Store parsed rows in cache for processing
        Cache::put('event_import_rows_' . session()->getId(), serialize($allRows), 3600);

        Notification::make()
            ->title("Fișier încărcat: {$this->csvTotalRows} bilete, " . count($this->discoveredTicketTypes) . " tipuri bilete")
            ->success()
            ->send();
    }

    public function startProcessing(): void
    {
        $this->stage = 3;
        $this->isProcessing = true;
        $this->processedOrders = 0;
        $this->processingStatus = 'Se procesează...';

        $this->processImport();
    }

    public function processImport(): void
    {
        $cacheKey = 'event_import_rows_' . session()->getId();
        $serialized = Cache::get($cacheKey);

        if (!$serialized) {
            Notification::make()->title('Datele importate au expirat. Reîncarcă fișierul.')->danger()->send();
            $this->stage = 2;
            $this->isProcessing = false;
            return;
        }

        /** @var ImportedRow[] $rows */
        $rows = unserialize($serialized);

        $tenantId = $this->resolveImportTenantId();
        if (!$tenantId) {
            Notification::make()->title('Nu s-a putut determina tenant-ul.')->danger()->send();
            $this->isProcessing = false;
            return;
        }

        $source = $this->eventFormData['import_source'] ?? 'iabilet';

        try {
            $service = new EventImportService();
            $result = $service->process($rows, $this->eventFormData, $tenantId, $source);

            $this->importResults = $result->toArray();
            $this->stage = 4;
            $this->isProcessing = false;

            // Clean up
            Cache::forget($cacheKey);
            if ($this->storedFilePath && file_exists($this->storedFilePath)) {
                @unlink($this->storedFilePath);
            }

            Notification::make()
                ->title('Import finalizat cu succes!')
                ->body("{$result->totalTickets} bilete, {$result->totalOrders} comenzi importate")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->isProcessing = false;
            $this->processingStatus = 'Eroare: ' . $e->getMessage();

            Notification::make()
                ->title('Eroare la import')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetImport(): void
    {
        $this->stage = 1;
        $this->eventFormData = [];
        $this->uploadedFile = null;
        $this->storedFilePath = null;
        $this->csvHeaders = [];
        $this->csvPreview = [];
        $this->csvTotalRows = 0;
        $this->discoveredTicketTypes = [];
        $this->isProcessing = false;
        $this->processedOrders = 0;
        $this->totalOrders = 0;
        $this->processingStatus = null;
        $this->importResults = null;

        Cache::forget('event_import_rows_' . session()->getId());
    }
}
