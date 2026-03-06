<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\NewsletterResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceContactTag;
use App\Models\MarketplaceEvent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class NewsletterResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceNewsletter::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Newsletters';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->components([
                SC\Section::make('Campaign Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Internal name for this campaign'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'sending' => 'Sending',
                                'sent' => 'Sent',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->disabled(fn ($record) => in_array($record?->status, ['sending', 'sent'])),
                    ])->columns(2),

                SC\Section::make('Recipients')
                    ->schema([
                        Forms\Components\Select::make('target_lists')
                            ->label('Contact Lists')
                            ->multiple()
                            ->options(function () use ($marketplace) {
                                return MarketplaceContactList::where('marketplace_client_id', $marketplace?->id)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->helperText('Select contact lists to send to'),
                        Forms\Components\Select::make('target_tags')
                            ->label('Contact Tags')
                            ->multiple()
                            ->options(function () use ($marketplace) {
                                return MarketplaceContactTag::where('marketplace_client_id', $marketplace?->id)
                                    ->pluck('name', 'id');
                            })
                            ->helperText('Optionally filter by tags'),
                        Forms\Components\Placeholder::make('recipient_preview')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Save the newsletter to preview recipients';
                                }
                                $count = $record->buildRecipientList()->count();
                                return "{$count} recipients will receive this newsletter";
                            }),
                    ])->columns(2),

                SC\Section::make('Sender Information')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('From Name')
                            ->default(fn () => $marketplace?->name)
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('from_email')
                            ->label('From Email')
                            ->email()
                            ->default(fn () => $marketplace?->contact_email)
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply-To Email')
                            ->email()
                            ->maxLength(255),
                    ])->columns(3),

                SC\Section::make('Email Content')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('preview_text')
                            ->maxLength(255)
                            ->helperText('Preview text shown in email client (optional)')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('body_sections')
                            ->label('Secțiuni Email')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tip secțiune')
                                    ->options([
                                        'text' => 'Text / Rich Content',
                                        'html' => 'HTML personalizat',
                                        'recommended_events' => 'Evenimente recomandate',
                                        'hand_picked_events' => 'Evenimente alese',
                                        'events_next_week' => 'Evenimente săptămâna viitoare',
                                        'events_next_month' => 'Evenimente luna viitoare',
                                        'button' => 'Buton CTA',
                                        'spacer' => 'Spațiu / Separator',
                                        'image' => 'Imagine',
                                    ])
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),

                                // Text section
                                Forms\Components\RichEditor::make('content')
                                    ->label('Conținut')
                                    ->visible(fn ($get) => $get('type') === 'text')
                                    ->columnSpanFull()
                                    ->helperText('Suportă variabile: {{customer_name}}, {{customer_email}}, {{event:ID:name}}, etc.'),

                                // HTML section
                                Forms\Components\Textarea::make('html_content')
                                    ->label('Cod HTML')
                                    ->visible(fn ($get) => $get('type') === 'html')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->helperText('HTML personalizat. Poți importa template-uri externe sau scrie cod HTML direct.'),

                                // Hand-picked events
                                Forms\Components\Select::make('event_ids')
                                    ->label('Selectează evenimente')
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                                        return MarketplaceEvent::where('marketplace_client_id', $marketplace?->id)
                                            ->where('status', 'approved')
                                            ->where('is_public', true)
                                            ->where('name', 'like', "%{$search}%")
                                            ->limit(20)
                                            ->pluck('name', 'id');
                                    })
                                    ->getOptionLabelsUsing(function (array $values) {
                                        return MarketplaceEvent::whereIn('id', $values)->pluck('name', 'id');
                                    })
                                    ->visible(fn ($get) => $get('type') === 'hand_picked_events')
                                    ->columnSpanFull()
                                    ->helperText('Caută și selectează evenimentele pe care vrei să le incluzi'),

                                // Event limit (for auto-populated sections)
                                Forms\Components\TextInput::make('limit')
                                    ->label('Număr maxim de evenimente')
                                    ->numeric()
                                    ->default(4)
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->visible(fn ($get) => in_array($get('type'), ['recommended_events', 'events_next_week', 'events_next_month']))
                                    ->maxWidth('xs'),

                                // Button fields
                                Forms\Components\TextInput::make('button_text')
                                    ->label('Text buton')
                                    ->default('Click aici')
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\TextInput::make('button_url')
                                    ->label('URL buton')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\ColorPicker::make('button_color')
                                    ->label('Culoare')
                                    ->default('#A51C30')
                                    ->visible(fn ($get) => $get('type') === 'button'),

                                // Image fields
                                Forms\Components\TextInput::make('image_url')
                                    ->label('URL imagine')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('image_link')
                                    ->label('Link la click (opțional)')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Text alternativ')
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),

                                // Spacer height
                                Forms\Components\TextInput::make('height')
                                    ->label('Înălțime (px)')
                                    ->numeric()
                                    ->default(20)
                                    ->visible(fn ($get) => $get('type') === 'spacer')
                                    ->maxWidth('xs'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                'text' => 'Text / Rich Content',
                                'html' => 'HTML personalizat',
                                'recommended_events' => 'Evenimente recomandate',
                                'hand_picked_events' => 'Evenimente alese (' . count($state['event_ids'] ?? []) . ')',
                                'events_next_week' => 'Evenimente săptămâna viitoare',
                                'events_next_month' => 'Evenimente luna viitoare',
                                'button' => 'Buton: ' . ($state['button_text'] ?? 'CTA'),
                                'spacer' => 'Spațiu / Separator',
                                'image' => 'Imagine',
                                default => 'Secțiune nouă',
                            })
                            ->defaultItems(0)
                            ->addActionLabel('Adaugă secțiune')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body_text')
                            ->label('Plain Text Version')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Versiune text simplu (opțional). Se generează automat dacă lipsește.'),
                    ]),

                SC\Section::make('Variabile disponibile')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="text-sm space-y-3">' .
                                '<div>' .
                                    '<p class="font-medium text-gray-700 dark:text-gray-300 mb-1">Variabile client (se completează per destinatar):</p>' .
                                    '<div class="flex flex-wrap gap-1.5">' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{customer_name}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{customer_email}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{marketplace_name}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{unsubscribe_url}}</code>' .
                                    '</div>' .
                                '</div>' .
                                '<div>' .
                                    '<p class="font-medium text-gray-700 dark:text-gray-300 mb-1">Variabile eveniment (înlocuiește ID cu id-ul evenimentului):</p>' .
                                    '<div class="flex flex-wrap gap-1.5">' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:name}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:date}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:venue}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:image}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:url}}</code>' .
                                        '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono">{{event:ID:price}}</code>' .
                                    '</div>' .
                                    '<p class="text-xs text-gray-500 mt-1">Exemplu: {{event:42:name}} va fi înlocuit cu numele evenimentului cu ID 42.</p>' .
                                '</div>' .
                                '</div>'
                            )),
                    ])
                    ->collapsed(),

                SC\Section::make('Scheduling')
                    ->schema([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Send At')
                            ->helperText('Leave empty to send immediately when you click "Send Newsletter"')
                            ->minDate(now()),
                    ])
                    ->visible(fn ($record) => !in_array($record?->status, ['sending', 'sent'])),

                SC\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('stats')
                            ->content(function ($record) {
                                if (!$record || $record->status === 'draft') {
                                    return 'Statistics will be available after sending';
                                }

                                $html = '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';
                                $html .= '<div class="bg-gray-100 p-4 rounded"><div class="text-2xl font-bold">' . number_format($record->total_recipients) . '</div><div class="text-sm text-gray-600">Total Recipients</div></div>';
                                $html .= '<div class="bg-green-100 p-4 rounded"><div class="text-2xl font-bold">' . number_format($record->sent_count) . '</div><div class="text-sm text-gray-600">Sent</div></div>';
                                $html .= '<div class="bg-blue-100 p-4 rounded"><div class="text-2xl font-bold">' . number_format($record->opened_count) . ' (' . $record->open_rate . '%)</div><div class="text-sm text-gray-600">Opened</div></div>';
                                $html .= '<div class="bg-purple-100 p-4 rounded"><div class="text-2xl font-bold">' . number_format($record->clicked_count) . ' (' . $record->click_rate . '%)</div><div class="text-sm text-gray-600">Clicked</div></div>';
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->visible(fn ($record) => $record && $record->status !== 'draft')
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'sending' => 'info',
                        'sent' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('Recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('open_rate')
                    ->label('Open Rate')
                    ->suffix('%')
                    ->visible(fn () => true),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Newsletter')
                    ->modalDescription('Are you sure you want to send this newsletter? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->createRecipients();
                        $record->startSending();
                        \App\Jobs\SendNewsletterJob::dispatch($record);
                    }),
                Action::make('schedule')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Send At')
                            ->required()
                            ->minDate(now()),
                    ])
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record, array $data) {
                        $record->createRecipients();
                        $record->schedule(new \DateTime($data['scheduled_at']));
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'scheduled')
                    ->action(fn ($record) => $record->cancel()),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->status = 'draft';
                        $new->scheduled_at = null;
                        $new->started_at = null;
                        $new->completed_at = null;
                        $new->total_recipients = 0;
                        $new->sent_count = 0;
                        $new->failed_count = 0;
                        $new->opened_count = 0;
                        $new->clicked_count = 0;
                        $new->unsubscribed_count = 0;
                        $new->save();

                        return redirect(static::getUrl('edit', ['record' => $new]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records) => $records->every(fn ($r) => $r->status === 'draft')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
        ];
    }
}
