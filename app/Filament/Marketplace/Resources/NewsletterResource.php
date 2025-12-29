<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\NewsletterResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceContactTag;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                        Forms\Components\RichEditor::make('body_html')
                            ->label('Email Body')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('newsletter-attachments'),
                        Forms\Components\Textarea::make('body_text')
                            ->label('Plain Text Version')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Optional plain text version for email clients that don\'t support HTML'),
                    ]),

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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Newsletter')
                    ->modalDescription('Are you sure you want to send this newsletter? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->createRecipients();
                        $record->startSending();
                        // TODO: Dispatch job to send emails
                    }),
                Tables\Actions\Action::make('schedule')
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
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'scheduled')
                    ->action(fn ($record) => $record->cancel()),
                Tables\Actions\Action::make('duplicate')
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
