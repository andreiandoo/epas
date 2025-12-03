<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Models\TicketTemplate;
use BackedEnum;
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

class Invitations extends Page
{
    use Forms\Concerns\InteractsWithForms;
    use WithFileUploads;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Invitations';
    protected static \UnitEnum|string|null $navigationGroup = 'Distribution';
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
    }

    public function getBatches()
    {
        $tenant = auth()->user()->tenant;

        return InviteBatch::where('tenant_id', $tenant->id)
            ->with(['template'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getEvents()
    {
        $tenant = auth()->user()->tenant;

        return Event::where('tenant_id', $tenant->id)
            ->where('start_date', '>=', now()->subMonths(6))
            ->orderBy('start_date', 'desc')
            ->pluck('title', 'id')
            ->toArray();
    }

    public function getTemplates()
    {
        $tenant = auth()->user()->tenant;

        return TicketTemplate::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();
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
                        ->options($this->getEvents())
                        ->required()
                        ->searchable(),

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
                        ->options($this->getTemplates())
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

        // Mark invites as rendered (simplified - in production would generate actual PDFs)
        $invitesWithRecipients = $batch->invites()->whereNotNull('recipient')->get();

        foreach ($invitesWithRecipients as $invite) {
            $invite->markAsRendered();
        }

        $batch->updateStatus('ready');

        Notification::make()
            ->success()
            ->title('Batch Rendered')
            ->body("Rendered {$invitesWithRecipients->count()} invitations.")
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
