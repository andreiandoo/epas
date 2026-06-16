<?php

namespace App\Filament\Marketplace\Resources\MarketplaceTodoResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceTodoResource;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceTodo;
use App\Models\MarketplaceTodoComment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ViewMarketplaceTodo extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MarketplaceTodoResource::class;
    protected string $view = 'filament.marketplace.resources.marketplace-todo-resource.pages.view-marketplace-todo';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin || $this->record->marketplace_client_id !== $admin->marketplace_client_id) {
            abort(403, 'TODO inaccesibil.');
        }
    }

    public function getTitle(): string
    {
        return $this->record->todo_number ?: ('#' . $this->record->id);
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

    /**
     * Full thread (comments + system events) for the timeline view.
     */
    public function getThreadComments()
    {
        return $this->record->comments()
            ->with('author')
            ->orderBy('created_at')
            ->get();
    }

    // ============================================================
    // Header actions
    // ============================================================

    protected function getHeaderActions(): array
    {
        $isClosed = $this->record->isClosed();

        return array_filter([
            Action::make('reply')
                ->label('Răspunde / Comentează')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->visible(fn () => !$isClosed)
                ->modalHeading('Comentariu nou')
                ->modalSubmitActionLabel('Trimite')
                ->modalWidth('3xl')
                ->form([
                    Forms\Components\RichEditor::make('body')
                        ->label('Mesaj')
                        ->required()
                        ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']),
                    Forms\Components\FileUpload::make('attachments')
                        ->label('Imagini (drag & drop)')
                        ->multiple()
                        ->image()
                        ->imageEditor()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->directory('marketplace-todos/' . $this->record->marketplace_client_id . '/' . $this->record->id)
                        ->disk('public')
                        ->visibility('public')
                        ->preserveFilenames(),
                ])
                ->action(fn (array $data) => $this->postComment($data)),

            Action::make('assign')
                ->label($this->record->assigned_to_marketplace_admin_id ? 'Reasignează' : 'Asignează')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->modalHeading('Asignează TODO-ul')
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
                ->label('Prioritate: ' . ($this->priorityLabel($this->record->priority)))
                ->icon('heroicon-o-flag')
                ->color($this->priorityColor($this->record->priority))
                ->modalHeading('Setează prioritate')
                ->form([
                    Forms\Components\Select::make('priority')
                        ->options(MarketplaceTodo::PRIORITY_LABELS)
                        ->required()
                        ->default($this->record->priority),
                ])
                ->action(fn (array $data) => $this->setPriority($data)),

            Action::make('status')
                ->label('Status: ' . (MarketplaceTodo::STATUS_LABELS[$this->record->status] ?? $this->record->status))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('gray')
                ->modalHeading('Schimbă status')
                ->form([
                    Forms\Components\Select::make('status')
                        ->options(MarketplaceTodo::STATUS_LABELS)
                        ->required()
                        ->default($this->record->status),
                ])
                ->action(fn (array $data) => $this->setStatus($data)),

            !$isClosed
                ? Action::make('resolve')
                    ->label('Marchează rezolvat')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn () => $this->resolve())
                : null,

            $this->record->status === MarketplaceTodo::STATUS_RESOLVED
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

            EditAction::make()->label('Editează'),
        ]);
    }

    // ============================================================
    // Action handlers
    // ============================================================

    protected function postComment(array $data): void
    {
        $admin = Auth::guard('marketplace_admin')->user();

        $attachments = $this->normalizeAttachments($data['attachments'] ?? []);

        MarketplaceTodoComment::create([
            'marketplace_client_id' => $this->record->marketplace_client_id,
            'marketplace_todo_id' => $this->record->id,
            'author_marketplace_admin_id' => $admin->id,
            'body' => $data['body'],
            'attachments' => $attachments ?: null,
        ]);

        $this->record->last_activity_at = now();

        // First non-creator reply records the first response timestamp.
        if (!$this->record->first_response_at
            && $admin->id !== (int) $this->record->created_by_marketplace_admin_id) {
            $this->record->first_response_at = now();
        }

        // If the responding admin is the assignee (or the creator commented
        // back), flip status to in_progress / awaiting_response accordingly.
        if ($this->record->status === MarketplaceTodo::STATUS_OPEN) {
            $this->record->status = $admin->id === (int) $this->record->created_by_marketplace_admin_id
                ? MarketplaceTodo::STATUS_OPEN
                : MarketplaceTodo::STATUS_AWAITING_RESPONSE;
        } elseif ($this->record->status === MarketplaceTodo::STATUS_IN_PROGRESS
            && $admin->id === (int) $this->record->assigned_to_marketplace_admin_id) {
            $this->record->status = MarketplaceTodo::STATUS_AWAITING_RESPONSE;
        } elseif ($this->record->status === MarketplaceTodo::STATUS_AWAITING_RESPONSE
            && $admin->id === (int) $this->record->created_by_marketplace_admin_id) {
            $this->record->status = MarketplaceTodo::STATUS_IN_PROGRESS;
        }

        $this->record->save();

        Notification::make()->title('Comentariu trimis')->success()->send();
    }

    protected function assignTo(array $data): void
    {
        $oldAssigneeId = $this->record->assigned_to_marketplace_admin_id;
        $newAssigneeId = $data['assigned_to_marketplace_admin_id'] ? (int) $data['assigned_to_marketplace_admin_id'] : null;

        if ($oldAssigneeId === $newAssigneeId) {
            return;
        }

        $this->record->assigned_to_marketplace_admin_id = $newAssigneeId;
        $this->record->last_activity_at = now();
        $this->record->save();

        $newName = $newAssigneeId ? (MarketplaceAdmin::find($newAssigneeId)?->name ?? '—') : null;
        $body = $newName ? "Asignat la: {$newName}" : 'Asignare scoasă';
        $this->logTimelineEvent(MarketplaceTodoComment::EVENT_ASSIGNED, $body);

        Notification::make()->title('Asignare actualizată')->success()->send();
    }

    protected function setPriority(array $data): void
    {
        $old = $this->record->priority;
        $new = $data['priority'];
        if ($old === $new) {
            return;
        }
        $this->record->priority = $new;
        $this->record->last_activity_at = now();
        $this->record->save();

        $oldLabel = MarketplaceTodo::PRIORITY_LABELS[$old] ?? $old;
        $newLabel = MarketplaceTodo::PRIORITY_LABELS[$new] ?? $new;
        $this->logTimelineEvent(
            MarketplaceTodoComment::EVENT_PRIORITY_CHANGED,
            "Prioritate: {$oldLabel} → {$newLabel}"
        );

        Notification::make()->title('Prioritate actualizată')->success()->send();
    }

    protected function setStatus(array $data): void
    {
        $old = $this->record->status;
        $new = $data['status'];
        if ($old === $new) {
            return;
        }
        $this->record->status = $new;
        $this->record->last_activity_at = now();
        if ($new === MarketplaceTodo::STATUS_RESOLVED) {
            $this->record->resolved_at = now();
        }
        if ($new === MarketplaceTodo::STATUS_CLOSED) {
            $this->record->closed_at = now();
        }
        if (in_array($old, [MarketplaceTodo::STATUS_RESOLVED, MarketplaceTodo::STATUS_CLOSED], true)
            && !in_array($new, [MarketplaceTodo::STATUS_RESOLVED, MarketplaceTodo::STATUS_CLOSED], true)) {
            $this->record->resolved_at = null;
            $this->record->closed_at = null;
        }
        $this->record->save();

        $oldLabel = MarketplaceTodo::STATUS_LABELS[$old] ?? $old;
        $newLabel = MarketplaceTodo::STATUS_LABELS[$new] ?? $new;
        $this->logTimelineEvent(
            MarketplaceTodoComment::EVENT_STATUS_CHANGED,
            "Status: {$oldLabel} → {$newLabel}"
        );

        Notification::make()->title('Status actualizat')->success()->send();
    }

    protected function resolve(): void
    {
        $this->record->status = MarketplaceTodo::STATUS_RESOLVED;
        $this->record->resolved_at = now();
        $this->record->last_activity_at = now();
        $this->record->save();
        $this->logTimelineEvent(MarketplaceTodoComment::EVENT_RESOLVED, 'TODO marcat ca rezolvat');
        Notification::make()->title('TODO rezolvat')->success()->send();
    }

    protected function close(): void
    {
        $this->record->status = MarketplaceTodo::STATUS_CLOSED;
        $this->record->closed_at = now();
        $this->record->last_activity_at = now();
        $this->record->save();
        $this->logTimelineEvent(MarketplaceTodoComment::EVENT_CLOSED, 'TODO închis definitiv');
        Notification::make()->title('TODO închis')->success()->send();
    }

    protected function reopen(): void
    {
        $this->record->status = MarketplaceTodo::STATUS_OPEN;
        $this->record->resolved_at = null;
        $this->record->closed_at = null;
        $this->record->last_activity_at = now();
        $this->record->save();
        $this->logTimelineEvent(MarketplaceTodoComment::EVENT_REOPENED, 'TODO redeschis');
        Notification::make()->title('TODO redeschis')->success()->send();
    }

    protected function logTimelineEvent(string $eventType, string $body): void
    {
        MarketplaceTodoComment::create([
            'marketplace_client_id' => $this->record->marketplace_client_id,
            'marketplace_todo_id' => $this->record->id,
            'author_marketplace_admin_id' => Auth::guard('marketplace_admin')->id(),
            'body' => $body,
            'event_type' => $eventType,
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function normalizeAttachments(array $paths): array
    {
        $disk = 'public';
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
                'url' => Storage::disk($disk)->url($p),
            ];
        }
        return $out;
    }

    protected function priorityLabel(?string $p): string
    {
        return MarketplaceTodo::PRIORITY_LABELS[$p] ?? 'Normală';
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
