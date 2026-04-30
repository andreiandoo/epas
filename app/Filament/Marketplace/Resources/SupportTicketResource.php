<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * MINIMAL resource — every callback / filter / virtual column / dot-notation
 * has been pulled out while we hunt the 500 on /marketplace/support-tickets.
 * Once this renders, features get added back one at a time.
 */
class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationLabel = 'Tichete suport';
    protected static ?string $modelLabel = 'tichet';
    protected static ?string $pluralModelLabel = 'tichete';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-lifebuoy';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Forms\Components\TextInput::make('subject')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('ticket_number'),
            Tables\Columns\TextColumn::make('subject')->limit(60),
            Tables\Columns\TextColumn::make('status'),
            Tables\Columns\TextColumn::make('priority'),
            Tables\Columns\TextColumn::make('opened_at')->dateTime('d M Y, H:i'),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
