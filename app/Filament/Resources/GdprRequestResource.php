<?php

namespace App\Filament\Resources;

use App\Models\Platform\GdprRequest;
use App\Models\Platform\CoreCustomer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Illuminate\Support\Facades\Response;

class GdprRequestResource extends Resource
{
    protected static ?string $model = GdprRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'GDPR Requests';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'GDPR Request';

    protected static ?string $pluralModelLabel = 'GDPR Requests';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::pending()->count() > 0 ? 'warning' : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\Select::make('request_type')
                            ->label('Request Type')
                            ->options(GdprRequest::REQUEST_TYPES)
                            ->required(),

                        Forms\Components\TextInput::make('email')
                            ->label('Customer Email')
                            ->email()
                            ->required()
                            ->helperText('The email address of the data subject'),

                        Forms\Components\Select::make('request_source')
                            ->label('Request Source')
                            ->options([
                                GdprRequest::SOURCE_CUSTOMER => 'Customer Request',
                                GdprRequest::SOURCE_ADMIN => 'Admin Request',
                            ])
                            ->default(GdprRequest::SOURCE_ADMIN)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->helperText('Any additional context for this request'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => GdprRequest::REQUEST_TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        GdprRequest::TYPE_DELETION => 'danger',
                        GdprRequest::TYPE_EXPORT => 'info',
                        GdprRequest::TYPE_ACCESS => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('request_source')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        GdprRequest::STATUS_PENDING => 'warning',
                        GdprRequest::STATUS_PROCESSING => 'info',
                        GdprRequest::STATUS_COMPLETED => 'success',
                        GdprRequest::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->placeholder('Pending'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('request_type')
                    ->options(GdprRequest::REQUEST_TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->options(GdprRequest::STATUSES),
            ])
            ->actions([
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Process GDPR Request')
                    ->modalDescription('Are you sure you want to process this request? This action cannot be undone for deletion requests.')
                    ->visible(fn ($record) => $record->status === GdprRequest::STATUS_PENDING)
                    ->action(function ($record) {
                        $success = $record->process();

                        if ($success) {
                            Notification::make()
                                ->success()
                                ->title('Request Processed')
                                ->body('The GDPR request has been processed successfully.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Processing Failed')
                                ->body('The request could not be processed. Check the notes for details.')
                                ->send();
                        }
                    }),

                Action::make('download_export')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === GdprRequest::STATUS_COMPLETED &&
                        in_array($record->request_type, [GdprRequest::TYPE_EXPORT, GdprRequest::TYPE_ACCESS]) &&
                        $record->export_data)
                    ->action(function ($record) {
                        $filename = "gdpr_export_{$record->id}_{$record->email}.json";
                        $content = json_encode($record->export_data, JSON_PRETTY_PRINT);

                        return Response::streamDownload(function () use ($content) {
                            echo $content;
                        }, $filename, [
                            'Content-Type' => 'application/json',
                        ]);
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('process_all')
                    ->label('Process Selected')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $processed = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            if ($record->status === GdprRequest::STATUS_PENDING) {
                                if ($record->process()) {
                                    $processed++;
                                } else {
                                    $failed++;
                                }
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Processing Complete')
                            ->body("Processed: {$processed}, Failed: {$failed}")
                            ->send();
                    }),
            ])
            ->defaultSort('requested_at', 'desc')
            ->emptyStateHeading('No GDPR Requests')
            ->emptyStateDescription('GDPR data subject requests will appear here.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('request_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => GdprRequest::REQUEST_TYPES[$state] ?? $state),

                        Infolists\Components\TextEntry::make('email'),

                        Infolists\Components\TextEntry::make('request_source')
                            ->badge(),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                GdprRequest::STATUS_PENDING => 'warning',
                                GdprRequest::STATUS_PROCESSING => 'info',
                                GdprRequest::STATUS_COMPLETED => 'success',
                                GdprRequest::STATUS_FAILED => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('processed_at')
                            ->dateTime()
                            ->placeholder('Not yet processed'),

                        Infolists\Components\TextEntry::make('completed_at')
                            ->dateTime()
                            ->placeholder('Not yet completed'),

                        Infolists\Components\TextEntry::make('processed_by')
                            ->placeholder('System'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Affected Data')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('affected_data')
                            ->label('')
                            ->placeholder('No data recorded'),
                    ])
                    ->visible(fn ($record) => !empty($record->affected_data)),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('')
                            ->placeholder('No notes'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\GdprRequestResource\Pages\ListGdprRequests::route('/'),
            'create' => \App\Filament\Resources\GdprRequestResource\Pages\CreateGdprRequest::route('/create'),
            'view' => \App\Filament\Resources\GdprRequestResource\Pages\ViewGdprRequest::route('/{record}'),
        ];
    }
}
