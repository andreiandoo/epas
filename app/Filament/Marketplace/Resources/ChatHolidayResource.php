<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ChatHolidayResource\Pages;
use App\Models\Chat\ChatHoliday;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatHolidayResource extends Resource
{
    protected static ?string $model = ChatHoliday::class;
    protected static ?string $navigationLabel = 'Zile libere chat';
    protected static ?string $modelLabel = 'zi liberă';
    protected static ?string $pluralModelLabel = 'zile libere';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 71;

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
                Forms\Components\DatePicker::make('date')
                    ->label('Data')
                    ->native(false)
                    ->required(),

                Forms\Components\TextInput::make('label')
                    ->label('Etichetă')
                    ->maxLength(191),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->label('Data')->date()->sortable(),
                Tables\Columns\TextColumn::make('label')->label('Etichetă'),
            ])
            ->defaultSort('date', 'desc')
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
            'index' => Pages\ListChatHolidays::route('/'),
            'create' => Pages\CreateChatHoliday::route('/create'),
            'edit' => Pages\EditChatHoliday::route('/{record}/edit'),
        ];
    }
}
