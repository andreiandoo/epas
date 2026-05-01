<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\SupportTicketResource\Pages;
use App\Models\MarketplaceAdmin;
use App\Models\SupportDepartment;
use App\Models\SupportProblemType;
use App\Models\SupportTicket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationLabel = 'Tichete suport';
    protected static ?string $modelLabel = 'tichet';
    protected static ?string $pluralModelLabel = 'tichete';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-lifebuoy';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationBadge(): ?string
    {
        $admin = Auth::guard('marketplace_admin')->user();
        if (!$admin) return null;
        $count = static::getEloquentQuery()
            ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $admin = Auth::guard('marketplace_admin')->user();
        // Eager loads live here instead of Table::modifyQueryUsing() — using
        // a Closure modifier on the table breaks the listing view in this
        // Filament build (the table's query reaches Filament's filter
        // wrapping with a model-less builder, blowing up at where(Closure)).
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $admin?->marketplace_client_id)
            ->with(['department', 'problemType', 'assignee']);
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Section::make('Detalii tichet')->schema([
                Forms\Components\TextInput::make('subject')
                    ->label('Subiect')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('support_department_id')
                    ->label('Departament')
                    ->options(fn () => SupportDepartment::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => $d->getTranslation('name', 'ro') ?: $d->slug])
                        ->all())
                    ->required(),

                Forms\Components\Select::make('support_problem_type_id')
                    ->label('Tip problemă')
                    ->options(fn () => SupportProblemType::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->getTranslation('name', 'ro') ?: $p->slug])
                        ->all())
                    ->nullable(),

                Forms\Components\Select::make('priority')
                    ->label('Prioritate')
                    ->options([
                        'low' => 'Scăzută',
                        'normal' => 'Normală',
                        'high' => 'Ridicată',
                        'urgent' => 'Urgentă',
                    ])
                    ->required()
                    ->default('normal'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Deschis',
                        'in_progress' => 'În lucru',
                        'awaiting_organizer' => 'Așteaptă răspuns',
                        'resolved' => 'Rezolvat',
                        'closed' => 'Închis',
                    ])
                    ->required(),

                Forms\Components\Select::make('assigned_to_marketplace_admin_id')
                    ->label('Asignat')
                    ->options(fn () => MarketplaceAdmin::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' — ' . $u->email])
                        ->all())
                    ->nullable()
                    ->searchable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Nr.')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subiect')
                    ->searchable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('opener_summary')
                    ->label('De la')
                    ->state(fn (SupportTicket $r) => static::openerLabel($r))
                    ->wrap(),

                Tables\Columns\TextColumn::make('department_name')
                    ->label('Departament')
                    ->state(fn (SupportTicket $r) => $r->department?->getTranslation('name', 'ro') ?? '—')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('assignee_name')
                    ->label('Asignat')
                    ->state(fn (SupportTicket $r) => $r->assignee?->name)
                    ->placeholder('— nealocat —')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritate')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'gray',
                        'low' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'low' => 'Scăzută',
                        'normal' => 'Normală',
                        'high' => 'Ridicată',
                        'urgent' => 'Urgentă',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'info',
                        // Organizer's turn to act → action needed by us
                        'in_progress' => 'warning',
                        // We just sent a reply → ball in organizer's court
                        'awaiting_organizer' => 'success',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Deschis',
                        'in_progress' => 'Așteaptă răspuns',
                        'awaiting_organizer' => 'Răspuns trimis',
                        'resolved' => 'Rezolvat',
                        'closed' => 'Închis',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Deschis')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Activitate')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Închis')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('support_department_id')
                    ->label('Departament')
                    ->options(fn () => SupportDepartment::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($d) => [$d->id => $d->getTranslation('name', 'ro') ?: $d->slug])
                        ->all()),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Deschis',
                        'in_progress' => 'În lucru',
                        'awaiting_organizer' => 'Așteaptă răspuns',
                        'resolved' => 'Rezolvat',
                        'closed' => 'Închis',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioritate')
                    ->options([
                        'low' => 'Scăzută',
                        'normal' => 'Normală',
                        'high' => 'Ridicată',
                        'urgent' => 'Urgentă',
                    ]),

                Tables\Filters\SelectFilter::make('assigned_to_marketplace_admin_id')
                    ->label('Asignat')
                    ->options(fn () => MarketplaceAdmin::query()
                        ->where('marketplace_client_id', Auth::guard('marketplace_admin')->user()?->marketplace_client_id)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => $u->name])
                        ->all()),
            ])
            ->recordUrl(fn (SupportTicket $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('close')
                        ->label('Închide selectate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update([
                            'status' => SupportTicket::STATUS_CLOSED,
                            'closed_at' => now(),
                            'last_activity_at' => now(),
                        ])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }

    /**
     * Polymorphic opener summary string for table rows.
     * Falls back to type+id if the opener record was deleted.
     */
    public static function openerLabel(SupportTicket $t): string
    {
        $opener = $t->opener;
        if (!$opener) {
            return ucfirst($t->opener_type) . ' #' . $t->opener_id;
        }
        return $opener->name
            ?? $opener->public_name
            ?? $opener->email
            ?? (ucfirst($t->opener_type) . ' #' . $t->opener_id);
    }
}
