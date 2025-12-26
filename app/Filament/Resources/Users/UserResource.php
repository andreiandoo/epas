<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static UnitEnum|string|null $navigationGroup = 'Core';
    protected static ?int $navigationSort = 30;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static BackedEnum|string|null $navigationLabel = 'Users';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(190),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),

            Forms\Components\Select::make('role')
                ->label('Role')
                ->options([
                    'super-admin' => 'Super Admin',
                    'admin' => 'Admin',
                    'editor' => 'Editor',
                    'tenant' => 'Tenant',
                ])
                ->default('editor')
                ->required()
                ->helperText('Super Admin has full access, Admin can manage resources, Editor has limited access, Tenant is organization owner'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->sortable()
                ->searchable()
                ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

            Tables\Columns\TextColumn::make('email')
                ->sortable()
                ->searchable(),

            Tables\Columns\BadgeColumn::make('role')
                ->label('Role')
                ->colors([
                    'danger' => 'super-admin',
                    'warning' => 'admin',
                    'success' => 'editor',
                    'primary' => 'tenant',
                ])
                ->sortable(),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->since(),
        ])
        ->actions([])
        ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
