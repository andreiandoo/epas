<?php

namespace App\Filament\Resources\SystemErrors\Pages;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use App\Models\SystemError;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ViewSystemError extends ViewRecord
{
    protected static string $resource = SystemErrorResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Summary')
                ->columns(4)
                ->schema([
                    TextEntry::make('created_at')->label('Time')->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('level_name')->label('Level')->badge()
                        ->color(fn (SystemError $r) => $r->severityColor()),
                    TextEntry::make('category')->label('Category')->badge()->color('gray'),
                    TextEntry::make('subcategory')->label('Subcategory')->placeholder('—'),
                    TextEntry::make('source')->label('Source'),
                    TextEntry::make('channel')->label('Channel')->placeholder('—'),
                    TextEntry::make('fingerprint')->label('Fingerprint')->copyable(),
                    TextEntry::make('similar_count')
                        ->label('Similar errors (last 24h)')
                        ->state(fn (SystemError $r) => SystemError::query()
                            ->where('fingerprint', $r->fingerprint)
                            ->where('created_at', '>=', now()->subDay())
                            ->count()),
                ]),

            Section::make('Message')->schema([
                TextEntry::make('message')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono text-sm']),
            ]),

            Tabs::make('Detail')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Stack trace')
                        ->visible(fn (SystemError $r) => filled($r->stack_trace))
                        ->schema([
                            TextEntry::make('exception_class')->label('Exception class')->placeholder('—')->color('danger'),
                            TextEntry::make('exception_location')
                                ->label('Location')
                                ->state(fn (SystemError $r) => $r->exception_file
                                    ? $r->exception_file . ':' . $r->exception_line
                                    : null)
                                ->placeholder('—'),
                            TextEntry::make('stack_trace')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono text-xs leading-tight'])
                                ->state(fn (SystemError $r) => $r->stack_trace),
                        ]),
                    Tab::make('Context')
                        ->visible(fn (SystemError $r) => is_array($r->context) && !empty($r->context))
                        ->schema([
                            TextEntry::make('context_json')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono text-xs leading-tight'])
                                ->state(fn (SystemError $r) => json_encode($r->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                        ]),
                    Tab::make('Request')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('request_url')->label('URL')->placeholder('—'),
                            TextEntry::make('request_method')->label('Method')->placeholder('—'),
                            TextEntry::make('request_ip')->label('IP')->placeholder('—'),
                            TextEntry::make('request_user_agent')->label('User-Agent')->placeholder('—')->columnSpanFull(),
                            TextEntry::make('request_user_type')->label('User type')->placeholder('—'),
                            TextEntry::make('request_user_id')->label('User ID')->placeholder('—'),
                            TextEntry::make('marketplace_client_id')->label('Marketplace client ID')->placeholder('—'),
                            TextEntry::make('tenant_id')->label('Tenant ID')->placeholder('—'),
                        ]),
                    Tab::make('Acknowledgement')
                        ->visible(fn (SystemError $r) => $r->isAcknowledged())
                        ->columns(2)
                        ->schema([
                            TextEntry::make('acknowledged_at')->label('Acknowledged at')->dateTime('Y-m-d H:i:s'),
                            TextEntry::make('acknowledger.name')->label('By')->placeholder('—'),
                            TextEntry::make('acknowledged_note')->label('Note')->placeholder('—')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copy_prompt')
                ->label('Copy as Prompt')
                ->icon('heroicon-o-clipboard-document')
                ->color('primary')
                ->modalHeading('Debug prompt for this error')
                ->modalContent(fn () => view(
                    'filament.resources.system-errors.copy-prompt-modal',
                    ['prompt' => $this->buildDebugPrompt()],
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Închide'),

            Action::make('open_similar')
                ->label('Open similar')
                ->icon('heroicon-o-square-2-stack')
                ->color('gray')
                ->url(fn () => SystemErrorResource::getUrl('index') . '?fp=' . $this->record->fingerprint),

            Action::make('acknowledge')
                ->label('Acknowledge')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => !$this->record->isAcknowledged())
                ->form([
                    Forms\Components\Textarea::make('note')->label('Note (optional)')->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'acknowledged_at' => now(),
                        'acknowledged_by' => auth()->id(),
                        'acknowledged_note' => $data['note'] ?? null,
                    ]);
                    Notification::make()->success()->title('Acknowledged')->send();
                    $this->refreshFormData(['acknowledged_at', 'acknowledged_by', 'acknowledged_note']);
                }),

            Action::make('delete_similar')
                ->label(fn () => 'Delete all with same fingerprint')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete all errors with this fingerprint?')
                ->modalDescription(fn () => sprintf(
                    'There are %s errors sharing this fingerprint (%s). All will be permanently deleted.',
                    number_format(SystemError::query()->where('fingerprint', $this->record->fingerprint)->count()),
                    substr((string) $this->record->fingerprint, 0, 12) . '…'
                ))
                ->modalSubmitActionLabel('Delete all')
                ->action(function () {
                    $fp = $this->record->fingerprint;
                    $deleted = DB::table('system_errors')
                        ->where('fingerprint', $fp)
                        ->delete();
                    Notification::make()->success()
                        ->title("Deleted {$deleted} errors with fingerprint " . substr((string) $fp, 0, 12) . '…')
                        ->send();
                    $this->redirect(SystemErrorResource::getUrl('index'));
                }),
        ];
    }

    /**
     * Build a self-contained debug prompt with all error details so it can be
     * pasted into a chat with the AI assistant for triage.
     */
    protected function buildDebugPrompt(): string
    {
        $r = $this->record;
        $contextJson = is_array($r->context) && !empty($r->context)
            ? json_encode($r->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '(none)';

        $stack = (string) ($r->stack_trace ?? '');
        if (mb_strlen($stack) > 6000) {
            $stack = mb_substr($stack, 0, 6000) . "\n…[truncated]";
        }

        $similarCount = SystemError::query()
            ->where('fingerprint', $r->fingerprint)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return <<<PROMPT
I have a production error in EventPilot/Tixello that I'd like you to debug.

## Error metadata
- Time:                 {$r->created_at}
- Severity:             {$r->level_name} (level={$r->level})
- Category:             {$r->category}
- Subcategory:          {$r->subcategory}
- Source:               {$r->source}
- Channel:              {$r->channel}
- Fingerprint:          {$r->fingerprint}
- Similar (last 7d):    {$similarCount}

## Exception
- Class:    {$r->exception_class}
- File:     {$r->exception_file}
- Line:     {$r->exception_line}

## Message
{$r->message}

## Stack trace
{$stack}

## Context
{$contextJson}

## Request
- URL:          {$r->request_url}
- Method:       {$r->request_method}
- IP:           {$r->request_ip}
- User-Agent:   {$r->request_user_agent}
- User type:    {$r->request_user_type}
- User ID:      {$r->request_user_id}
- Marketplace client ID: {$r->marketplace_client_id}
- Tenant ID:    {$r->tenant_id}

## Task
Please:
1. Identify the root cause of this error.
2. Locate the file/code likely responsible (use the stack trace + request URL).
3. Propose a fix and, if you have repo access, apply it. Match the project's style.
4. Tell me whether to acknowledge this error in /admin/system-errors or whether the same fix will fully resolve all {$similarCount} similar occurrences.

PROMPT;
    }
}
