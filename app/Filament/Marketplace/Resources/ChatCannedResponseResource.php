<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ChatCannedResponseResource\Pages;
use App\Models\Chat\ChatCannedResponse;
use App\Models\SupportDepartment;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatCannedResponseResource extends Resource
{
    protected static ?string $model = ChatCannedResponse::class;
    protected static ?string $navigationLabel = 'Răspunsuri predefinite';
    protected static ?string $modelLabel = 'răspuns predefinit';
    protected static ?string $pluralModelLabel = 'răspunsuri predefinite';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Chat';
    protected static ?int $navigationSort = 72;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) (Auth::guard('marketplace_admin')->user()?->marketplaceClient?->hasMicroservice('live-chat'));
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        $adminClientId = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;

        return $form->components([
            Section::make()->schema([
                Forms\Components\TextInput::make('shortcut')
                    ->label('Scurtătură')
                    ->helperText('ex: /refund')
                    ->required()
                    ->maxLength(64),

                Forms\Components\TextInput::make('title')
                    ->label('Titlu')
                    ->required()
                    ->maxLength(191),

                Forms\Components\Select::make('support_department_id')
                    ->label('Departament')
                    ->options(fn () => SupportDepartment::query()
                        ->where('marketplace_client_id', $adminClientId)
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => (is_array($d->name) ? ($d->name['ro'] ?? $d->name['en'] ?? reset($d->name)) : $d->name)])
                        ->toArray())
                    ->nullable()
                    ->searchable()
                    ->placeholder('Toate departamentele'),

                Forms\Components\Textarea::make('body')
                    ->label('Conținut')
                    ->required()
                    ->rows(5)
                    ->helperText('Poți folosi {name}, {event} ca variabile.'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activ')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shortcut')->label('Scurtătură')->badge(),
                Tables\Columns\TextColumn::make('title')->label('Titlu')->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departament')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['ro'] ?? $state['en'] ?? reset($state)) : $state),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Ordine')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatCannedResponses::route('/'),
            'create' => Pages\CreateChatCannedResponse::route('/create'),
            'edit' => Pages\EditChatCannedResponse::route('/{record}/edit'),
        ];
    }
}
