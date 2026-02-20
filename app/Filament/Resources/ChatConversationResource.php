<?php

namespace App\Filament\Resources;

use App\Models\ChatConversation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use BackedEnum;
use UnitEnum;

class ChatConversationResource extends Resource
{
    protected static ?string $model = ChatConversation::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'AI Chat';
    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 35;
    protected static ?string $modelLabel = 'Chat Conversation';
    protected static ?string $pluralModelLabel = 'Chat Conversations';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['open', 'escalated'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $escalated = static::getModel()::where('status', 'escalated')->count();
        return $escalated > 0 ? 'danger' : 'info';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conversation Details')
                    ->schema([
                        Forms\Components\Placeholder::make('marketplace')
                            ->label('Marketplace')
                            ->content(fn ($record) => $record?->marketplaceClient?->name ?? 'N/A'),

                        Forms\Components\Placeholder::make('customer_info')
                            ->label('Customer')
                            ->content(fn ($record) => $record?->marketplaceCustomer
                                ? $record->marketplaceCustomer->first_name . ' ' . $record->marketplaceCustomer->last_name . ' <' . $record->marketplaceCustomer->email . '>'
                                : 'Guest (Session: ' . substr($record->session_id ?? '', 0, 12) . '...)'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'resolved' => 'Resolved',
                                'escalated' => 'Escalated',
                            ])
                            ->disabled(),

                        Forms\Components\Placeholder::make('page_url')
                            ->label('Page URL')
                            ->content(fn ($record) => $record?->page_url ?? 'N/A'),

                        Forms\Components\Placeholder::make('message_count')
                            ->label('Messages')
                            ->content(fn ($record) => $record?->getMessageCount() ?? 0),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label('Started')
                            ->content(fn ($record) => $record?->created_at?->format('d.m.Y H:i')),
                    ])->columns(2),

                Section::make('Messages')
                    ->schema([
                        Forms\Components\Placeholder::make('messages_timeline')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return 'No messages';

                                $messages = $record->messages()
                                    ->whereIn('role', ['user', 'assistant'])
                                    ->orderBy('created_at')
                                    ->get();

                                if ($messages->isEmpty()) return 'No messages';

                                $html = '<div style="max-height: 500px; overflow-y: auto; padding: 8px;">';

                                foreach ($messages as $msg) {
                                    $isUser = $msg->role === 'user';
                                    $bgColor = $isUser ? '#dbeafe' : '#f1f5f9';
                                    $label = $isUser ? 'Customer' : 'AI Assistant';
                                    $time = $msg->created_at?->format('H:i');
                                    $ratingText = '';
                                    if ($msg->rating === 1) $ratingText = ' 👍';
                                    if ($msg->rating === -1) $ratingText = ' 👎';

                                    $content = e($msg->content);
                                    $content = nl2br($content);

                                    $html .= "<div style='margin-bottom: 12px; padding: 10px 14px; background: {$bgColor}; border-radius: 10px;'>"
                                        . "<div style='font-size: 12px; color: #64748b; margin-bottom: 4px;'><strong>{$label}</strong> · {$time}{$ratingText}</div>"
                                        . "<div style='font-size: 14px; line-height: 1.5;'>{$content}</div>"
                                        . "</div>";
                                }

                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('metadata_display')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->metadata) return 'No metadata';

                                $meta = $record->metadata;
                                $parts = [];
                                if (!empty($meta['ip'])) $parts[] = 'IP: ' . $meta['ip'];
                                if (!empty($meta['user_agent'])) $parts[] = 'UA: ' . substr($meta['user_agent'], 0, 100);

                                return implode("\n", $parts) ?: 'No metadata';
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceCustomer.email')
                    ->label('Customer')
                    ->searchable()
                    ->default('Guest')
                    ->description(fn ($record) => $record->marketplaceCustomer
                        ? $record->marketplaceCustomer->first_name . ' ' . $record->marketplaceCustomer->last_name
                        : 'Session: ' . substr($record->session_id ?? '', 0, 12)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'info',
                        'resolved' => 'success',
                        'escalated' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Messages')
                    ->sortable(),

                Tables\Columns\TextColumn::make('page_url')
                    ->label('Page')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('escalated_at')
                    ->label('Escalated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                        'escalated' => 'Escalated',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ChatConversationResource\Pages\ListChatConversations::route('/'),
            'view' => ChatConversationResource\Pages\ViewChatConversation::route('/{record}'),
        ];
    }
}
