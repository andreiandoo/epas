<?php

namespace App\Filament\Resources\Ledger;

use App\Models\PlatformCredential;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use BackedEnum;
use UnitEnum;

class PlatformCredentialResource extends Resource
{
    protected static ?string $model = PlatformCredential::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 50;
    protected static ?string $modelLabel = 'Credential';
    protected static ?string $pluralModelLabel = 'Credentials Ledger';
    protected static ?string $navigationLabel = 'Ledger';

    /**
     * Only super-admin can access this resource
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Platform Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Platform Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Facebook, Product Hunt, GitHub'),

                    Forms\Components\TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://...'),

                    Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options(PlatformCredential::getCategoryLabels())
                        ->required()
                        ->searchable(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),

            SC\Section::make('Credentials')
                ->schema([
                    Forms\Components\TextInput::make('username')
                        ->label('Username')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => $state) // Will be encrypted by model
                        ->hintIcon('heroicon-o-lock-closed', tooltip: 'Password will be stored encrypted'),
                ])->columns(3),

            SC\Section::make('Additional Information')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Any additional notes about this account...'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->url(fn ($record) => $record->url, true)
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn ($state) => PlatformCredential::getCategoryLabels()[$state] ?? $state)
                    ->colors([
                        'primary' => 'social_content',
                        'success' => 'saas_review',
                        'warning' => 'startup_directory',
                        'info' => 'business_listing',
                        'danger' => 'developer_tech',
                        'gray' => fn ($state) => in_array($state, ['integration_marketplace', 'community_forum']),
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\ViewColumn::make('password')
                    ->label('Password')
                    ->view('filament.tables.columns.password-reveal'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(PlatformCredential::getCategoryLabels()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->getTitleFromRecordUsing(fn ($record) => $record->category_label),
            ])
            ->defaultGroup('category')
            ->recordUrl(fn ($record) => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformCredentials::route('/'),
            'create' => Pages\CreatePlatformCredential::route('/create'),
            'edit' => Pages\EditPlatformCredential::route('/{record}/edit'),
        ];
    }
}
