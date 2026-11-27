<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ChatBlocklistResource\Pages;
use App\Models\Chat\ChatBlocklistEntry;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatBlocklistResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    protected static ?string $model = ChatBlocklistEntry::class;
    protected static ?string $navigationLabel = 'Blocklist chat';
    protected static ?string $modelLabel = 'intrare blocklist';
    protected static ?string $pluralModelLabel = 'blocklist chat';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-no-symbol';
    protected static \UnitEnum|string|null $navigationGroup = 'Chat';
    protected static ?int $navigationSort = 73;

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
        return $form->components([
            Section::make()->schema([
                Forms\Components\Select::make('type')
                    ->label(__('Tip'))
                    ->required()
                    ->options(['ip' => __('IP'), 'email' => __('Email')]),

                Forms\Components\TextInput::make('value')
                    ->label(__('Valoare'))
                    ->required()
                    ->maxLength(191)
                    ->helperText(__('Adresa IP sau emailul de blocat')),

                Forms\Components\TextInput::make('reason')
                    ->label(__('Motiv'))
                    ->maxLength(255),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label(__('Expiră la'))
                    ->native(false)
                    ->helperText(__('Gol = blocare permanentă')),

                Forms\Components\Placeholder::make('creator_name')
                    ->label(__('Adăugat de'))
                    ->content(fn ($record) => $record?->creator?->name ?? '—')
                    ->visibleOn('edit'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label(__('Tip'))->badge(),
                Tables\Columns\TextColumn::make('value')->label(__('Valoare'))->searchable()->copyable(),
                Tables\Columns\TextColumn::make('reason')->label(__('Motiv'))->limit(40),
                Tables\Columns\TextColumn::make('creator.name')->label(__('Adăugat de'))->placeholder('—'),
                Tables\Columns\TextColumn::make('expires_at')->label(__('Expiră'))->dateTime()->placeholder(__('Permanent')),
                Tables\Columns\TextColumn::make('created_at')->label(__('Adăugat'))->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListChatBlocklists::route('/'),
            'create' => Pages\CreateChatBlocklist::route('/create'),
            'edit' => Pages\EditChatBlocklist::route('/{record}/edit'),
        ];
    }
}
