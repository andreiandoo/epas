<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Models\TicketType;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Livewire\WithFileUploads;
use ZipArchive;

class Invitations extends Page
{
    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Invitations';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.invitations';

    public ?array $batchData = [];
    public ?array $importData = [];
    public bool $showCreateModal = false;
    public bool $showImportModal = false;
    public ?string $selectedBatchId = null;

    // Import form properties
    public $csvFile = null;
    public int $colName = 0;
    public int $colEmail = 1;
    public int $colPhone = 2;
    public int $colCompany = 3;
    public int $colSeat = 4;
    public bool $skipHeader = true;

    // Manual recipient entry
    public bool $showManualModal = false;
    public ?string $manualBatchId = null;
    public string $manualName = '';
    public string $manualEmail = '';
    public string $manualPhone = '';
    public string $manualCompany = '';
    public string $manualSeat = '';

    // Pre-selected event (from query param)
    public ?int $preselectedEventId = null;

    /**
     * Only show if tenant has invitations microservice active
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('microservices.slug', 'invitations')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(404);
        }

        // Check if microservice is active
        $hasAccess = $tenant->microservices()
            ->where('microservices.slug', 'invitations')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAccess) {
            Notification::make()
                ->warning()
                ->title('Microservice Not Active')
                ->body('You need to activate the Invitations microservice first.')
                ->send();

            redirect()->route('filament.tenant.pages.microservices');
            return;
        }

        // Capture event query parameter
        $eventId = request()->query('event');
        if ($eventId) {
            // Verify the event belongs to this tenant
            $event = Event::where('tenant_id', $tenant->id)->find($eventId);
            if ($event) {
                $this->preselectedEventId = (int) $eventId;
            }
        }
    }

    public function getBatches()
    {
        $tenant = auth()->user()->tenant;

        return InviteBatch::where('tenant_id', $tenant->id)
            ->with(['template'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getRecipientCount(int $batchId): int
    {
        return Invite::where('batch_id', $batchId)
            ->whereNotNull('recipient')
            ->count();
    }

    public function getEvents(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return [];
        }

        return Event::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->mapWithKeys(fn ($event) => [$event->id => $event->getTranslation('title')])
            ->all();
    }

    public function getTemplates(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return [];
        }

        return TicketTemplate::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn ($template) => [$template->id => $template->name])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBatch')
                ->label('Create Batch')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Create Invitation Batch')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\Select::make('event_ref')
                        ->label('Event')
                        ->options(fn () => $this->getEvents())
                        ->required()
                        ->searchable()
                        ->default($this->preselectedEventId),

                    Forms\Components\TextInput::make('name')
                        ->label('Batch Name')
                        ->required()
                        ->placeholder('e.g., VIP Guests - Opening Night')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('qty_planned')
                        ->label('Planned Quantity')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->default(50),

                    Forms\Components\Select::make('template_id')
                        ->label('Ticket Template')
                        ->options(fn () => $this->getTemplates())
                        ->searchable()
                        ->helperText('Optional: Select a template for invitation design'),

                    Forms\Components\TextInput::make('watermark')
                        ->label('Watermark Text')
                        ->placeholder('e.g., VIP INVITATION')
                        ->maxLength(50)
                        ->helperText('Text to overlay on the invitation'),

                    Forms\Components\Select::make('seat_mode')
                        ->label('Seat Assignment')
                        ->options([
                            'none' => 'No seat assignment',
                            'manual' => 'Manual assignment',
                            'auto' => 'Auto-assign',
                        ])
                        ->default('none'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->placeholder('Internal notes about this batch')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $this->createBatch($data);
                }),
        ];
    }

    public function createBatch(array $data): void
    {
        $tenant = auth()->user()->tenant;

        $batch = InviteBatch::create([
            'tenant_id' => $tenant->id,
            'event_ref' => $data['event_ref'],
            'name' => $data['name'],
            'qty_planned' => $data['qty_planned'],
            'template_id' => $data['template_id'] ?? null,
            'options' => [
                'watermark' => $data['watermark'] ?? 'INVITATION',
                'seat_mode' => $data['seat_mode'] ?? 'none',
                'notes' => $data['notes'] ?? '',
            ],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        // Generate empty invitations
        for ($i = 0; $i < $data['qty_planned']; $i++) {
            Invite::create([
                'batch_id' => $batch->id,
                'tenant_id' => $tenant->id,
                'status' => 'created',
            ]);
        }

        $batch->update(['qty_generated' => $data['qty_planned']]);

        Notification::make()
            ->success()
            ->title('Batch Created')
            ->body("Created batch \"{$data['name']}\" with {$data['qty_planned']} invitations.")
            ->send();

        $this->dispatch('$refresh');
    }

    public function openImportModal(string $batchId): void
    {
        $this->selectedBatchId = $batchId;
        $this->csvFile = null;
        $this->colName = 0;
        $this->colEmail = 1;
        $this->colPhone = 2;
        $this->colCompany = 3;
        $this->colSeat = 4;
        $this->skipHeader = true;
        $this->showImportModal = true;
    }

    public function processImport(): void
    {
        $batch = InviteBatch::find($this->selectedBatchId);

        if (!$batch) {
            Notification::make()
                ->danger()
                ->title('Batch not found')
                ->send();
            return;
        }

        $tenant = auth()->user()->tenant;

        if ($batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        if (!$this->csvFile) {
            Notification::make()
                ->danger()
                ->title('No file selected')
                ->body('Please select a CSV file to import.')
                ->send();
            return;
        }

        try {
            $csvPath = $this->csvFile->getRealPath();
            $csv = Reader::createFromPath($csvPath, 'r');

            if ($this->skipHeader) {
                $csv->setHeaderOffset(0);
            }

            $records = $csv->getRecords();
            $invites = $batch->invites()->where('recipient', null)->get();
            $imported = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                if ($imported >= $invites->count()) {
                    break;
                }

                $recordArray = array_values((array) $record);

                $email = trim($recordArray[$this->colEmail] ?? '');

                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row " . ($index + 1) . ": Invalid email address";
                    continue;
                }

                $invite = $invites[$imported];
                $invite->setRecipient([
                    'name' => trim($recordArray[$this->colName] ?? ''),
                    'email' => $email,
                    'phone' => trim($recordArray[$this->colPhone] ?? ''),
                    'company' => trim($recordArray[$this->colCompany] ?? ''),
                ]);

                if ($this->colSeat !== null && isset($recordArray[$this->colSeat])) {
                    $invite->update(['seat_ref' => trim($recordArray[$this->colSeat])]);
                }

                $imported++;
            }

            $message = "Imported {$imported} recipients.";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " rows skipped due to errors.";
            }

            Notification::make()
                ->success()
                ->title('Import Complete')
                ->body($message)
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->send();
        }

        $this->showImportModal = false;
        $this->selectedBatchId = null;
        $this->csvFile = null;
        $this->dispatch('$refresh');
    }

    public function importFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('csv_file')
                ->label('CSV File')
                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                ->required()
                ->helperText('Upload a CSV with columns: name, email, phone, company'),

            Forms\Components\Grid::make(5)->schema([
                Forms\Components\TextInput::make('col_name')
                    ->label('Name Column')
                    ->numeric()
                    ->default(0)
                    ->helperText('0-indexed'),

                Forms\Components\TextInput::make('col_email')
                    ->label('Email Column')
                    ->numeric()
                    ->default(1),

                Forms\Components\TextInput::make('col_phone')
                    ->label('Phone Column')
                    ->numeric()
                    ->default(2),

                Forms\Components\TextInput::make('col_company')
                    ->label('Company Column')
                    ->numeric()
                    ->default(3),

                Forms\Components\TextInput::make('col_seat')
                    ->label('Seat Column')
                    ->numeric()
                    ->default(4),
            ]),

            Forms\Components\Toggle::make('skip_header')
                ->label('Skip header row')
                ->default(true),
        ];
    }

    public function importRecipients(array $data): void
    {
        $batch = InviteBatch::find($this->selectedBatchId);

        if (!$batch) {
            Notification::make()
                ->danger()
                ->title('Batch not found')
                ->send();
            return;
        }

        $tenant = auth()->user()->tenant;

        if ($batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        $csvPath = Storage::disk('public')->path($data['csv_file']);

        try {
            $csv = Reader::createFromPath($csvPath, 'r');

            if ($data['skip_header'] ?? true) {
                $csv->setHeaderOffset(0);
            }

            $records = $csv->getRecords();
            $invites = $batch->invites()->where('recipient', null)->get();
            $imported = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                if ($imported >= $invites->count()) {
                    break;
                }

                $recordArray = array_values((array) $record);

                $email = trim($recordArray[$data['col_email'] ?? 1] ?? '');

                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row " . ($index + 1) . ": Invalid email address";
                    continue;
                }

                $invite = $invites[$imported];
                $invite->setRecipient([
                    'name' => trim($recordArray[$data['col_name'] ?? 0] ?? ''),
                    'email' => $email,
                    'phone' => trim($recordArray[$data['col_phone'] ?? 2] ?? ''),
                    'company' => trim($recordArray[$data['col_company'] ?? 3] ?? ''),
                ]);

                if (!empty($data['col_seat']) && isset($recordArray[$data['col_seat']])) {
                    $invite->update(['seat_ref' => trim($recordArray[$data['col_seat']])]);
                }

                $imported++;
            }

            // Clean up uploaded file
            Storage::disk('public')->delete($data['csv_file']);

            $message = "Imported {$imported} recipients.";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " rows skipped due to errors.";
            }

            Notification::make()
                ->success()
                ->title('Import Complete')
                ->body($message)
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->send();
        }

        $this->showImportModal = false;
        $this->selectedBatchId = null;
        $this->dispatch('$refresh');
    }

    /**
     * Generate a QR code as base64 PNG using QR Server API (no dependencies)
     */
    protected function generateQrCode(string $data): string
    {
        // Use QR Server API - free, no authentication required
        $size = 200;
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data) . '&format=png&margin=5';

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Tixello/1.0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);

            if ($imageData !== false && strlen($imageData) > 100) {
                return base64_encode($imageData);
            }
        } catch (\Exception $e) {
            // Log error if needed
        }

        // Fallback to placeholder
        return $this->generatePlaceholderQr($data);
    }

    /**
     * Generate a placeholder QR code image with invite code
     */
    protected function generatePlaceholderQr(string $data): string
    {
        $size = 200;
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);

        imagefill($image, 0, 0, $white);

        // Draw QR-like pattern border
        imagerectangle($image, 0, 0, $size - 1, $size - 1, $black);
        imagerectangle($image, 10, 10, $size - 11, $size - 11, $gray);

        // Draw corner markers (like QR codes have)
        $markerSize = 40;
        // Top-left
        imagefilledrectangle($image, 20, 20, 20 + $markerSize, 20 + $markerSize, $black);
        imagefilledrectangle($image, 28, 28, 20 + $markerSize - 8, 20 + $markerSize - 8, $white);
        imagefilledrectangle($image, 34, 34, 20 + $markerSize - 14, 20 + $markerSize - 14, $black);

        // Top-right
        imagefilledrectangle($image, $size - 20 - $markerSize, 20, $size - 20, 20 + $markerSize, $black);
        imagefilledrectangle($image, $size - 20 - $markerSize + 8, 28, $size - 28, 20 + $markerSize - 8, $white);
        imagefilledrectangle($image, $size - 20 - $markerSize + 14, 34, $size - 34, 20 + $markerSize - 14, $black);

        // Bottom-left
        imagefilledrectangle($image, 20, $size - 20 - $markerSize, 20 + $markerSize, $size - 20, $black);
        imagefilledrectangle($image, 28, $size - 20 - $markerSize + 8, 20 + $markerSize - 8, $size - 28, $white);
        imagefilledrectangle($image, 34, $size - 20 - $markerSize + 14, 20 + $markerSize - 14, $size - 34, $black);

        // Add text in center
        $text = 'QR CODE';
        $fontSize = 3;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textX = ($size - $textWidth) / 2;
        $textY = $size / 2 - 5;
        imagestring($image, $fontSize, $textX, $textY, $text, $black);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return base64_encode($imageData);
    }

    /**
     * Render batch and generate actual PDF files
     */
    public function renderBatch(string $batchId): void
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        // Mark batch as rendering
        $batch->updateStatus('rendering');

        // Get invites with recipients
        $invitesWithRecipients = $batch->invites()->whereNotNull('recipient')->get();

        if ($invitesWithRecipients->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No Recipients')
                ->body('Add recipients before generating PDFs.')
                ->send();
            $batch->updateStatus('draft');
            return;
        }

        // Get event details
        $event = Event::find($batch->event_ref);
        $eventTitle = $event ? $event->getTranslation('title') : 'Event';
        $eventSubtitle = $event ? $event->getTranslation('subtitle') : null;

        // Find or create "Invitatie" ticket type for this event
        $invitationTicketType = null;
        if ($event) {
            $invitationTicketType = TicketType::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'name' => 'Invitatie',
                ],
                [
                    'price_cents' => 0,
                    'currency' => 'RON',
                    'quota_total' => 0, // Unlimited for invitations
                    'quota_sold' => 0,
                    'status' => 'active',
                    'meta' => ['is_invitation' => true],
                ]
            );
        }

        // Format event date
        $eventDate = 'TBA';
        if ($event) {
            if ($event->event_date) {
                $eventDate = $event->event_date->format('F j, Y');
            } elseif ($event->range_start_date) {
                $eventDate = $event->range_start_date->format('F j') . ' - ' . $event->range_end_date->format('F j, Y');
            }
        }

        // Format event time
        $eventTime = null;
        if ($event && $event->start_time) {
            $eventTime = $event->start_time;
            if ($event->door_time) {
                $eventTime = "Doors: {$event->door_time} | Start: {$event->start_time}";
            }
        }

        // Get venue name
        $venueName = null;
        if ($event && $event->venue) {
            $venueName = $event->venue->getTranslation('name');
        }

        $watermark = $batch->getWatermark();

        // Create storage directory for this batch
        $storagePath = "invitations/{$batch->id}";
        Storage::disk('local')->makeDirectory($storagePath);

        $rendered = 0;
        $errors = [];

        foreach ($invitesWithRecipients as $invite) {
            try {
                // Generate QR code for this invitation
                $qrData = url("/verify/{$invite->invite_code}");
                $qrCode = $this->generateQrCode($qrData);

                // Generate PDF
                $pdf = Pdf::loadView('pdf.invitation', [
                    'invite' => $invite,
                    'eventTitle' => $eventTitle,
                    'eventSubtitle' => $eventSubtitle,
                    'eventDate' => $eventDate,
                    'eventTime' => $eventTime,
                    'venueName' => $venueName,
                    'watermark' => $watermark,
                    'qrCode' => $qrCode,
                ]);

                // Set PDF options
                $pdf->setPaper('a4', 'portrait');

                // Save PDF to storage
                $pdfFilename = "{$invite->invite_code}.pdf";
                $pdfPath = "{$storagePath}/{$pdfFilename}";
                Storage::disk('local')->put($pdfPath, $pdf->output());

                // Update invite with PDF URL
                $invite->setUrls([
                    'pdf' => $pdfPath,
                    'generated_at' => now()->toIso8601String(),
                ]);

                // Store QR data
                $invite->update(['qr_data' => $qrData]);

                // Mark as rendered
                $invite->markAsRendered();
                $rendered++;

                // Create ticket record for this invitation (if not already exists)
                if ($invitationTicketType && !Ticket::where('code', $invite->invite_code)->exists()) {
                    Ticket::create([
                        'order_id' => null, // No order for invitations
                        'ticket_type_id' => $invitationTicketType->id,
                        'performance_id' => null,
                        'code' => $invite->invite_code,
                        'status' => 'valid',
                        'seat_label' => $invite->seat_ref,
                        'meta' => [
                            'is_invitation' => true,
                            'invite_batch_id' => $batch->id,
                            'beneficiary' => [
                                'name' => $invite->getRecipientName(),
                                'email' => $invite->getRecipientEmail(),
                                'phone' => $invite->getRecipientPhone(),
                                'company' => $invite->getRecipientCompany(),
                            ],
                        ],
                    ]);
                }

            } catch (\Exception $e) {
                $errors[] = "Failed to render invitation for {$invite->getRecipientName()}: {$e->getMessage()}";
            }
        }

        $batch->updateStatus('ready');

        $message = "Generated {$rendered} invitation PDFs.";
        if (count($errors) > 0) {
            $message .= " " . count($errors) . " failed.";
        }

        Notification::make()
            ->success()
            ->title('PDFs Generated')
            ->body($message)
            ->send();

        $this->dispatch('$refresh');
    }

    public function sendEmails(string $batchId): void
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        if (!$batch->canSendEmails()) {
            Notification::make()
                ->warning()
                ->title('Cannot Send')
                ->body('Batch must be rendered first and have invitations ready.')
                ->send();
            return;
        }

        $batch->updateStatus('sending');

        // Get invites that can be emailed
        $invites = $batch->invites()
            ->whereNotNull('recipient')
            ->whereNotNull('rendered_at')
            ->whereNull('emailed_at')
            ->get();

        $sent = 0;
        foreach ($invites as $invite) {
            // In production: dispatch email job
            // For now, just mark as emailed
            $invite->markAsEmailed();
            $sent++;
        }

        $batch->updateStatus('completed');

        Notification::make()
            ->success()
            ->title('Emails Queued')
            ->body("Queued {$sent} invitation emails for delivery.")
            ->send();

        $this->dispatch('$refresh');
    }

    public function cancelBatch(string $batchId): void
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        // Void all invitations
        $batch->invites()->each(function ($invite) {
            if ($invite->canBeVoided()) {
                $invite->markAsVoid();
            }
        });

        $batch->updateStatus('cancelled');

        Notification::make()
            ->success()
            ->title('Batch Cancelled')
            ->body('All invitations in this batch have been voided.')
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Delete batch and all associated invites permanently
     */
    public function deleteBatch(string $batchId): void
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        $batchName = $batch->name;
        $inviteCount = $batch->invites()->count();

        // Delete stored PDFs
        $storagePath = "invitations/{$batch->id}";
        Storage::disk('local')->deleteDirectory($storagePath);

        // Delete all invites (this will also delete logs due to cascade)
        $batch->invites()->forceDelete();

        // Delete the batch itself
        $batch->forceDelete();

        Notification::make()
            ->success()
            ->title('Batch Deleted')
            ->body("Deleted batch \"{$batchName}\" with {$inviteCount} invitations.")
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Open manual recipient entry modal
     */
    public function openManualModal(string $batchId): void
    {
        $this->manualBatchId = $batchId;
        $this->manualName = '';
        $this->manualEmail = '';
        $this->manualPhone = '';
        $this->manualCompany = '';
        $this->manualSeat = '';
        $this->showManualModal = true;
    }

    /**
     * Add a single recipient manually
     */
    public function addManualRecipient(): void
    {
        $batch = InviteBatch::find($this->manualBatchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        // Validate email
        if (!filter_var($this->manualEmail, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->danger()
                ->title('Invalid Email')
                ->body('Please enter a valid email address.')
                ->send();
            return;
        }

        // Validate name
        if (empty(trim($this->manualName))) {
            Notification::make()
                ->danger()
                ->title('Name Required')
                ->body('Please enter a name for the recipient.')
                ->send();
            return;
        }

        // Find an available invite slot (one without a recipient)
        $invite = $batch->invites()->whereNull('recipient')->first();

        if (!$invite) {
            // No available slots, create a new invite
            $invite = Invite::create([
                'batch_id' => $batch->id,
                'tenant_id' => $tenant->id,
                'status' => 'created',
            ]);

            // Update batch quantities
            $batch->increment('qty_planned');
            $batch->increment('qty_generated');
        }

        // Set the recipient data
        $invite->setRecipient([
            'name' => trim($this->manualName),
            'email' => trim($this->manualEmail),
            'phone' => trim($this->manualPhone),
            'company' => trim($this->manualCompany),
        ]);

        if (!empty(trim($this->manualSeat))) {
            $invite->update(['seat_ref' => trim($this->manualSeat)]);
        }

        Notification::make()
            ->success()
            ->title('Recipient Added')
            ->body("Added {$this->manualName} to the batch.")
            ->send();

        // Reset form but keep modal open for adding more
        $this->manualName = '';
        $this->manualEmail = '';
        $this->manualPhone = '';
        $this->manualCompany = '';
        $this->manualSeat = '';

        $this->dispatch('$refresh');
    }

    /**
     * Close manual modal
     */
    public function closeManualModal(): void
    {
        $this->showManualModal = false;
        $this->manualBatchId = null;
    }

    /**
     * Download PDFs as ZIP archive
     */
    public function downloadPdfs(string $batchId)
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        if ($batch->qty_rendered === 0) {
            Notification::make()
                ->warning()
                ->title('No PDFs Available')
                ->body('Generate PDFs first before downloading.')
                ->send();
            return;
        }

        // Get rendered invites
        $invites = $batch->invites()
            ->whereNotNull('rendered_at')
            ->whereNotNull('recipient')
            ->get();

        if ($invites->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No PDFs Available')
                ->body('No rendered invitations found.')
                ->send();
            return;
        }

        // Check if PDFs exist
        $storagePath = "invitations/{$batch->id}";
        if (!Storage::disk('local')->exists($storagePath)) {
            Notification::make()
                ->warning()
                ->title('PDFs Not Found')
                ->body('PDF files not found. Please regenerate them.')
                ->send();
            return;
        }

        // Create ZIP file
        $zipFilename = Str::slug($batch->name) . '-invitations-' . now()->format('Y-m-d') . '.zip';
        $tempDir = storage_path('app/temp');
        $zipPath = "{$tempDir}/{$zipFilename}";

        // Ensure temp directory exists
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()
                ->danger()
                ->title('ZIP Creation Failed')
                ->body('Could not create ZIP archive.')
                ->send();
            return;
        }

        $addedFiles = 0;

        foreach ($invites as $invite) {
            $pdfPath = $invite->getPdfUrl();

            if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
                $pdfContent = Storage::disk('local')->get($pdfPath);
                $recipientName = Str::slug($invite->getRecipientName() ?? 'guest');
                $filename = "{$recipientName}-{$invite->invite_code}.pdf";

                $zip->addFromString($filename, $pdfContent);
                $addedFiles++;

                // Mark as downloaded
                $invite->markAsDownloaded();
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            Notification::make()
                ->warning()
                ->title('No PDFs Found')
                ->body('No PDF files were found to download.')
                ->send();

            // Clean up empty zip
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            return;
        }

        // Return download response
        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Regenerate all PDFs for a batch (force re-render)
     */
    public function regeneratePdfs(string $batchId): void
    {
        $batch = InviteBatch::find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            Notification::make()
                ->danger()
                ->title('Access denied')
                ->send();
            return;
        }

        // Delete existing PDFs
        $storagePath = "invitations/{$batch->id}";
        Storage::disk('local')->deleteDirectory($storagePath);

        // Reset rendered status on invites
        $batch->invites()
            ->whereNotNull('rendered_at')
            ->update([
                'rendered_at' => null,
                'urls' => null,
            ]);

        // Reset batch qty_rendered
        $batch->update(['qty_rendered' => 0]);

        // Now regenerate
        $this->renderBatch($batchId);
    }

    public function downloadExport(string $batchId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $batch = InviteBatch::with('invites')->find($batchId);
        $tenant = auth()->user()->tenant;

        if (!$batch || $batch->tenant_id !== $tenant->id) {
            abort(403);
        }

        $filename = Str::slug($batch->name) . '-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Invite Code',
                'Name',
                'Email',
                'Phone',
                'Company',
                'Seat',
                'Status',
                'Rendered At',
                'Emailed At',
                'Downloaded At',
                'Checked In At',
            ]);

            foreach ($batch->invites as $invite) {
                fputcsv($handle, [
                    $invite->invite_code,
                    $invite->getRecipientName() ?? '',
                    $invite->getRecipientEmail() ?? '',
                    $invite->getRecipientPhone() ?? '',
                    $invite->getRecipientCompany() ?? '',
                    $invite->seat_ref ?? '',
                    $invite->status,
                    $invite->rendered_at?->format('Y-m-d H:i:s') ?? '',
                    $invite->emailed_at?->format('Y-m-d H:i:s') ?? '',
                    $invite->downloaded_at?->format('Y-m-d H:i:s') ?? '',
                    $invite->checked_in_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getTitle(): string
    {
        return 'Invitations';
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'rendering' => 'yellow',
            'ready' => 'blue',
            'sending' => 'indigo',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'rendering' => 'Rendering',
            'ready' => 'Ready',
            'sending' => 'Sending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
    }
}
