<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EmailLogResource\Pages;
use App\Models\Marketplace\MarketplaceEmailLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailLogResource extends Resource
{
    protected static ?string $model = MarketplaceEmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 21;
    protected static ?string $navigationLabel = 'Email History';
    protected static ?string $modelLabel = 'Email Log';
    protected static ?string $pluralModelLabel = 'Email History';

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();
        return $tenant && $tenant->isMarketplace();
    }

    public static function canCreate(): bool
    {
        return false; // Email logs are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Email logs should not be edited
    }

    public static function canDelete($record): bool
    {
        return false; // Email logs should be kept for audit purposes
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = filament()->getTenant();

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id)
            ->with(['organizer', 'template']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->recipient_name),

                Tables\Columns\TextColumn::make('recipient_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'info',
                        'organizer' => 'success',
                        'admin' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label('Template')
                    ->placeholder('No template')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),

                Tables\Filters\SelectFilter::make('recipient_type')
                    ->label('Recipient Type')
                    ->options([
                        'customer' => 'Customer',
                        'organizer' => 'Organizer',
                        'admin' => 'Admin',
                    ]),

                Tables\Filters\SelectFilter::make('organizer_id')
                    ->label('Organizer')
                    ->relationship('organizer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->modalHeading('Resend Email')
                    ->modalDescription('Are you sure you want to resend this email?')
                    ->action(function ($record) {
                        // TODO: Implement resend logic
                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Feature coming soon')
                            ->body('Email resend functionality will be available soon.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Email Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('recipient_email')
                            ->label('Recipient Email'),
                        Infolists\Components\TextEntry::make('recipient_name')
                            ->label('Recipient Name'),
                        Infolists\Components\TextEntry::make('recipient_type')
                            ->label('Recipient Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'customer' => 'info',
                                'organizer' => 'success',
                                'admin' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'sent' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),
                    ])->columns(4),

                Infolists\Components\Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Subject'),
                        Infolists\Components\TextEntry::make('body')
                            ->label('Body')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Timing')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('sent_at')
                            ->label('Sent')
                            ->dateTime()
                            ->placeholder('Not sent'),
                        Infolists\Components\TextEntry::make('failed_at')
                            ->label('Failed')
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columns(3),

                Infolists\Components\Section::make('Error Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->status === 'failed'),

                Infolists\Components\Section::make('Related')
                    ->schema([
                        Infolists\Components\TextEntry::make('template.name')
                            ->label('Template Used')
                            ->placeholder('No template'),
                        Infolists\Components\TextEntry::make('organizer.name')
                            ->label('Related Organizer')
                            ->placeholder('N/A'),
                    ])->columns(2),

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->metadata))
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = filament()->getTenant();
        if (!$tenant || !$tenant->isMarketplace()) {
            return null;
        }

        $failed = static::getEloquentQuery()->failed()->count();
        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
