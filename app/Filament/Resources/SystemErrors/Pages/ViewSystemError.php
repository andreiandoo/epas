<?php

namespace App\Filament\Resources\SystemErrors\Pages;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use App\Models\SystemError;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewSystemError extends ViewRecord
{
    protected static string $resource = SystemErrorResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Summary')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Time')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('level_name')
                            ->label('Level')
                            ->badge()
                            ->color(fn (SystemError $r) => $r->severityColor()),
                        TextEntry::make('category')
                            ->label('Category')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('subcategory')
                            ->label('Subcategory')
                            ->placeholder('—'),
                        TextEntry::make('source')
                            ->label('Source'),
                        TextEntry::make('channel')
                            ->label('Channel')
                            ->placeholder('—'),
                        TextEntry::make('fingerprint')
                            ->label('Fingerprint')
                            ->copyable(),
                        TextEntry::make('similar_count')
                            ->label('Similar errors (last 24h)')
                            ->state(fn (SystemError $r) => SystemError::query()
                                ->where('fingerprint', $r->fingerprint)
                                ->where('created_at', '>=', now()->subDay())
                                ->count()),
                    ]),

                Section::make('Message')
                    ->schema([
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
                                TextEntry::make('exception_class')
                                    ->label('Exception class')
                                    ->placeholder('—')
                                    ->color('danger'),
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
                            ->schema([
                                TextEntry::make('request_url')->label('URL')->placeholder('—'),
                                TextEntry::make('request_method')->label('Method')->placeholder('—'),
                                TextEntry::make('request_ip')->label('IP')->placeholder('—'),
                                TextEntry::make('request_user_agent')->label('User-Agent')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('request_user_type')->label('User type')->placeholder('—'),
                                TextEntry::make('request_user_id')->label('User ID')->placeholder('—'),
                                TextEntry::make('marketplace_client_id')->label('Marketplace client ID')->placeholder('—'),
                                TextEntry::make('tenant_id')->label('Tenant ID')->placeholder('—'),
                            ])
                            ->columns(2),
                        Tab::make('Acknowledgement')
                            ->visible(fn (SystemError $r) => $r->isAcknowledged())
                            ->schema([
                                TextEntry::make('acknowledged_at')
                                    ->label('Acknowledged at')
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('acknowledger.name')
                                    ->label('By')
                                    ->placeholder('—'),
                                TextEntry::make('acknowledged_note')
                                    ->label('Note')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
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
            Action::make('open_similar')
                ->label('Open similar')
                ->icon('heroicon-o-square-2-stack')
                ->color('gray')
                ->url(fn () => route('filament.admin.resources.system-errors.index', [
                    'tableFilters[fingerprint_filter][fingerprint]' => $this->record->fingerprint,
                ])),
        ];
    }
}
