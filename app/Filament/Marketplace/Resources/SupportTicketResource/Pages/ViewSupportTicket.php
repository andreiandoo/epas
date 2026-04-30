<?php

namespace App\Filament\Marketplace\Resources\SupportTicketResource\Pages;

use App\Filament\Marketplace\Resources\SupportTicketResource;
use App\Models\Event;
use App\Models\MarketplaceAdmin;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ViewSupportTicket extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SupportTicketResource::class;
    protected string $view = 'filament.marketplace.resources.support-ticket-resource.pages.view-support-ticket';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin || $this->record->marketplace_client_id !== $admin->marketplace_client_id) {
            abort(403, 'Tichet inaccesibil.');
        }
    }

    public function getTitle(): string
    {
        return $this->record->ticket_number ?: ('#' . $this->record->id);
    }

    public function getSubheading(): ?string
    {
        return $this->record->subject;
    }

    /**
     * Load thread messages (including internal notes — staff sees everything).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SupportTicketMessage>
     */
    public function getMessages()
    {
        return $this->record->messages()
            ->with('author')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Polymorphic opener (organizer/customer) detail block for the sidebar.
     */
    public function getOpener(): array
    {
        $opener = $this->record->opener;
        if (!$opener) {
            return [
                'type' => $this->record->opener_type,
                'id' => $this->record->opener_id,
                'name' => null,
                'email' => null,
                'phone' => null,
                'company' => null,
                'company_url' => null,
                'past_tickets_count' => 0,
            ];
        }
        $companyUrl = null;
        if ($this->record->opener_type === 'organizer') {
            // Best-effort link into the OrganizerResource if it exists.
            if (class_exists(\App\Filament\Marketplace\Resources\OrganizerResource::class)) {
                try {
                    $companyUrl = \App\Filament\Marketplace\Resources\OrganizerResource::getUrl('edit', ['record' => $opener->id]);
                } catch (\Throwable) {
                    $companyUrl = null;
                }
            }
        }

        $past = SupportTicket::query()
            ->where('opener_type', $this->record->opener_type)
            ->where('opener_id', $this->record->opener_id)
            ->where('id', '!=', $this->record->id)
            ->count();

        return [
            'type' => $this->record->opener_type,
            'id' => $this->record->opener_id,
            'name' => $opener->name ?? $opener->public_name ?? null,
            'email' => $opener->email ?? null,
            'phone' => $opener->phone ?? null,
            'company' => $opener->legal_name ?? $opener->company_name ?? null,
            'company_url' => $companyUrl,
            'past_tickets_count' => $past,
        ];
    }

    /**
     * Active events (published and not yet ended) of this organizer,
     * with a link to their event-edit page when EventResource exists.
     */
    public function getActiveEvents(): array
    {
        if ($this->record->opener_type !== 'organizer') {
            return [];
        }

        $events = Event::query()
            ->where('marketplace_client_id', $this->record->marketplace_client_id)
            ->where('marketplace_organizer_id', $this->record->opener_id)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('event_date')
                  ->orWhere('event_date', '>=', now()->startOfDay())
                  ->orWhereNull('range_end_date')
                  ->orWhere('range_end_date', '>=', now()->startOfDay());
            })
            ->orderBy('event_date')
            ->limit(20)
            ->get();

        return $events->map(function (Event $e) {
            $url = null;
            if (class_exists(\App\Filament\Marketplace\Resources\EventResource::class)) {
                try {
                    $url = \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $e->id]);
                } catch (\Throwable) {
                    $url = null;
                }
            }
            $title = $e->getTranslation('title', 'ro') ?: ($e->getTranslation('title', 'en') ?: ('Eveniment #' . $e->id));
            return [
                'id' => $e->id,
                'title' => $title,
                'starts_at' => $e->event_date ?? $e->range_start_date,
                'tickets_sold' => (int) ($e->total_tickets_sold ?? 0),
                'url' => $url,
            ];
        })->all();
    }

    public function getRequestContext(): array
    {
        return (array) ($this->record->context ?? []);
    }

    // ============================================================
    // Header actions: reply, move, assign, priority, close, reopen
    // ============================================================

    protected function getHeaderActions(): array
    {
        $isClosed = $this->record->isClosed();

        return array_filter([
            Action::make('reply')
                ->label('Răspunde')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => !$isClosed)
                ->modalHeading('Răspunde la tichet')
                ->modalSubmitActionLabel('Trimite')
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label('Mesaj')
                        ->required()
                        ->rows(5)
                        ->maxLength(10000),
                    Forms\Components\Toggle::make('is_internal_note')
                        ->label('Notă internă (vizibil doar staff)')
                        ->default(false)
                        ->helperText('Organizatorul nu va vedea acest mesaj.'),
                    Forms\Components\FileUpload::make('attachments')
                        ->label('Atașamente (jpg/png/pdf, max 3 MB)')
                        ->multiple()
                        ->maxFiles((int) config('support.attachments.max_per_message', 5))
                        ->maxSize((int) config('support.attachments.max_size_kb', 3072))
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                        ->directory('support-tickets/' . $this->record->id)
                        ->disk(config('support.attachments.storage_disk', 'public'))
                        ->visibility('public')
                        ->preserveFilenames(),
                ])
                ->action(fn (array $data) => $this->postReply($data)),

            Action::make('moveDepartment')
                ->label('Mută la alt departament')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('gray')
                ->modalHeading('Mută tichetul')
                ->form([
                    Forms\Components\Select::make('support_department_id')
                        ->label('Departament')
                        ->options(fn () => SupportDepartment::query()
                            ->where('marketplace_client_id', $this->record->marketplace_client_id)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($d) => [$d->id => $d->getTranslation('name', 'ro') ?: $d->slug])
                            ->all())
                        ->required()
                        ->default($this->record->support_department_id),
                    Forms\Components\Textarea::make('reason')
                        ->label('Motiv (opțional, va apărea ca notă internă)')
                        ->rows(2),
                ])
                ->action(fn (array $data) => $this->moveDepartment($data)),

            Action::make('assign')
                ->label($this->record->assigned_to_marketplace_admin_id ? 'Reasignează' : 'Asignează')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->modalHeading('Asignează tichetul')
                ->form([
                    Forms\Components\Select::make('assigned_to_marketplace_admin_id')
                        ->label('Asignat')
                        ->options(fn () => MarketplaceAdmin::query()
                            ->where('marketplace_client_id', $this->record->marketplace_client_id)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' — ' . $u->email])
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->default($this->record->assigned_to_marketplace_admin_id),
                ])
                ->action(fn (array $data) => $this->assignTo($data)),

            Action::make('priority')
                ->label('Prioritate: ' . $this->priorityLabel($this->record->priority))
                ->icon('heroicon-o-flag')
                ->color($this->priorityColor($this->record->priority))
                ->modalHeading('Setează prioritate')
                ->form([
                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Scăzută',
                            'normal' => 'Normală',
                            'high' => 'Ridicată',
                            'urgent' => 'Urgentă',
                        ])
                        ->required()
                        ->default($this->record->priority),
                ])
                ->action(fn (array $data) => $this->setPriority($data)),

            !$isClosed
                ? Action::make('resolve')
                    ->label('Marchează rezolvat')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn () => $this->resolve())
                : null,

            $this->record->status === SupportTicket::STATUS_RESOLVED
                ? Action::make('close')
                    ->label('Închide definitiv')
                    ->icon('heroicon-o-archive-box-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->close())
                : null,

            $isClosed
                ? Action::make('reopen')
                    ->label('Redeschide')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(fn () => $this->reopen())
                : null,
        ]);
    }

    // ============================================================
    // Action handlers
    // ============================================================

    protected function postReply(array $data): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $isInternal = (bool) ($data['is_internal_note'] ?? false);

        $attachments = $this->normalizeAttachments($data['attachments'] ?? []);

        $msg = SupportTicketMessage::create([
            'marketplace_client_id' => $this->record->marketplace_client_id,
            'support_ticket_id' => $this->record->id,
            'author_type' => 'staff',
            'author_id' => $admin->id,
            'body' => $data['body'],
            'is_internal_note' => $isInternal,
            'attachments' => $attachments ?: null,
        ]);

        $this->record->last_activity_at = now();
        if (!$isInternal) {
            // Public reply — flip to awaiting_organizer + record first response.
            if (!$this->record->first_response_at) {
                $this->record->first_response_at = now();
            }
            if ($this->record->status === SupportTicket::STATUS_OPEN
                || $this->record->status === SupportTicket::STATUS_IN_PROGRESS) {
                $this->record->status = SupportTicket::STATUS_AWAITING_ORGANIZER;
            }
        } elseif ($this->record->status === SupportTicket::STATUS_OPEN) {
            // Internal note doesn't notify the opener but still moves the
            // ticket out of "untriaged".
            $this->record->status = SupportTicket::STATUS_IN_PROGRESS;
        }
        // Auto-claim on first staff message if nobody else owns it yet.
        if (!$this->record->assigned_to_marketplace_admin_id) {
            $this->record->assigned_to_marketplace_admin_id = $admin->id;
        }
        $this->record->save();

        Notification::make()
            ->title($isInternal ? 'Notă internă salvată' : 'Răspuns trimis')
            ->success()
            ->send();
    }

    protected function moveDepartment(array $data): void
    {
        $oldDeptId = $this->record->support_department_id;
        $newDeptId = (int) $data['support_department_id'];
        if ($oldDeptId === $newDeptId && empty($data['reason'])) {
            return;
        }

        $this->record->support_department_id = $newDeptId;
        $this->record->last_activity_at = now();
        $this->record->save();

        if (!empty($data['reason']) || $oldDeptId !== $newDeptId) {
            $oldName = SupportDepartment::find($oldDeptId)?->getTranslation('name', 'ro') ?? '—';
            $newName = SupportDepartment::find($newDeptId)?->getTranslation('name', 'ro') ?? '—';
            $body = "Tichet mutat: {$oldName} → {$newName}";
            if (!empty($data['reason'])) {
                $body .= "\n\nMotiv: " . $data['reason'];
            }
            SupportTicketMessage::create([
                'marketplace_client_id' => $this->record->marketplace_client_id,
                'support_ticket_id' => $this->record->id,
                'author_type' => 'staff',
                'author_id' => Auth::guard('marketplace_admin')->id(),
                'body' => $body,
                'is_internal_note' => true,
            ]);
        }

        Notification::make()->title('Tichet mutat')->success()->send();
    }

    protected function assignTo(array $data): void
    {
        $newAssigneeId = $data['assigned_to_marketplace_admin_id'] ? (int) $data['assigned_to_marketplace_admin_id'] : null;
        $this->record->assigned_to_marketplace_admin_id = $newAssigneeId;
        $this->record->last_activity_at = now();
        $this->record->save();
        Notification::make()->title('Asignare actualizată')->success()->send();
    }

    protected function setPriority(array $data): void
    {
        $this->record->priority = $data['priority'];
        $this->record->last_activity_at = now();
        $this->record->save();
        Notification::make()->title('Prioritate actualizată')->success()->send();
    }

    protected function resolve(): void
    {
        $this->record->status = SupportTicket::STATUS_RESOLVED;
        $this->record->resolved_at = now();
        $this->record->last_activity_at = now();
        $this->record->save();
        Notification::make()->title('Tichet marcat ca rezolvat')->success()->send();
    }

    protected function close(): void
    {
        $this->record->status = SupportTicket::STATUS_CLOSED;
        $this->record->closed_at = now();
        $this->record->last_activity_at = now();
        $this->record->save();
        Notification::make()->title('Tichet închis')->success()->send();
    }

    protected function reopen(): void
    {
        $this->record->status = SupportTicket::STATUS_OPEN;
        $this->record->resolved_at = null;
        $this->record->closed_at = null;
        $this->record->last_activity_at = now();
        $this->record->save();
        Notification::make()->title('Tichet redeschis')->success()->send();
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Filament's FileUpload returns a list of stored paths (strings).
     * Convert each into the metadata shape the rest of the system expects:
     * { path, original_name, mime, size, disk }.
     */
    protected function normalizeAttachments(array $paths): array
    {
        $disk = (string) config('support.attachments.storage_disk', 'public');
        $out = [];
        foreach ($paths as $p) {
            if (!is_string($p) || $p === '') continue;
            $abs = Storage::disk($disk)->path($p);
            $out[] = [
                'path' => $p,
                'original_name' => basename($p),
                'mime' => function_exists('mime_content_type') && file_exists($abs) ? mime_content_type($abs) : null,
                'size' => file_exists($abs) ? filesize($abs) : null,
                'disk' => $disk,
            ];
        }
        return $out;
    }

    protected function priorityLabel(?string $p): string
    {
        return match ($p) {
            'low' => 'Scăzută',
            'high' => 'Ridicată',
            'urgent' => 'Urgentă',
            default => 'Normală',
        };
    }

    protected function priorityColor(?string $p): string
    {
        return match ($p) {
            'urgent' => 'danger',
            'high' => 'warning',
            'low' => 'info',
            default => 'gray',
        };
    }
}
