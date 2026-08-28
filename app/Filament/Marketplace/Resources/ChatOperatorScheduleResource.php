<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ChatOperatorScheduleResource\Pages;
use App\Models\Chat\ChatOperatorSchedule;
use App\Models\MarketplaceAdmin;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChatOperatorScheduleResource extends Resource
{
    protected static ?string $model = ChatOperatorSchedule::class;
    protected static ?string $navigationLabel = 'Program operatori chat';
    protected static ?string $modelLabel = 'program';
    protected static ?string $pluralModelLabel = 'program operatori';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static \UnitEnum|string|null $navigationGroup = 'Chat';
    protected static ?int $navigationSort = 70;

    protected static array $days = [
        0 => 'Duminică',
        1 => 'Luni',
        2 => 'Marți',
        3 => 'Miercuri',
        4 => 'Joi',
        5 => 'Vineri',
        6 => 'Sâmbătă',
    ];

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
                Forms\Components\Select::make('marketplace_admin_id')
                    ->label('Operator')
                    ->options(function () {
                        return MarketplaceAdmin::query()
                            ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                            ->where('status', 'active')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('day_of_week')
                    ->label('Zi')
                    ->options(static::$days)
                    ->required(),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Ora început')
                    ->seconds(false)
                    ->required(),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Ora sfârșit')
                    ->seconds(false)
                    ->required(),

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
                Tables\Columns\TextColumn::make('operator.name')->label('Operator'),
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Zi')
                    ->formatStateUsing(fn ($state) => static::$days[$state] ?? $state),
                Tables\Columns\TextColumn::make('start_time')->label('Ora început'),
                Tables\Columns\TextColumn::make('end_time')->label('Ora sfârșit'),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
            ])
            ->defaultSort('day_of_week')
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
            'index' => Pages\ListChatOperatorSchedules::route('/'),
            'create' => Pages\CreateChatOperatorSchedule::route('/create'),
            'edit' => Pages\EditChatOperatorSchedule::route('/{record}/edit'),
        ];
    }
}
