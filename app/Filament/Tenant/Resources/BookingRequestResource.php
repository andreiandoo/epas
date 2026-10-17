<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\BookingRequestResource\Pages;
use App\Models\TenantBookingRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cererile de booking venite de pe site (pagina artistului și /booking): cine, pentru ce
 * artist, ce eveniment, când, unde, ce buget. Agenția schimbă starea și își notează
 * discuția; restul datelor rămân cum le-a trimis solicitantul.
 */
class BookingRequestResource extends Resource
{
    protected static ?string $model = TenantBookingRequest::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 60;
    protected static ?string $slug = 'cereri-booking';
    protected static ?string $modelLabel = 'cerere de booking';
    protected static ?string $pluralModelLabel = 'Cereri de booking';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Sales');
    }

    public static function getNavigationLabel(): string
    {
        return 'Cereri de booking';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = TenantBookingRequest::where('tenant_id', auth()->user()->tenant?->id)->where('status', 'new')->count();
        return $n ? (string) $n : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        return in_array($tenant?->tenant_type, [\App\Enums\TenantType::Agency, \App\Enums\TenantType::Artist], true)
            || TenantBookingRequest::where('tenant_id', $tenant?->id)->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()->tenant?->id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Cererea')->schema([
                Forms\Components\Placeholder::make('who')->label('Solicitant')
                    ->content(fn ($record) => trim($record->name . ($record->company ? ' · ' . $record->company : ''))),
                Forms\Components\Placeholder::make('contact')->label('Contact')
                    ->content(fn ($record) => new \Illuminate\Support\HtmlString('<a href="mailto:' . e($record->email) . '" class="underline">' . e($record->email) . '</a>' . ($record->phone ? ' · <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $record->phone)) . '" class="underline">' . e($record->phone) . '</a>' : ''))),
                Forms\Components\Placeholder::make('artist')->label('Artist')->content(fn ($record) => $record->artist_name ?: 'Fără artist anume'),
                Forms\Components\Placeholder::make('what')->label('Eveniment')
                    ->content(fn ($record) => implode(' · ', array_filter([
                        TenantBookingRequest::EVENT_TYPES[$record->event_type] ?? $record->event_type,
                        $record->event_date?->format('d.m.Y'), $record->city, $record->venue,
                        $record->capacity ? $record->capacity . ' persoane' : null,
                        $record->budget ? 'buget ' . $record->budget : null,
                    ])) ?: '—'),
                Forms\Components\Placeholder::make('msg')->label('Mesaj')->content(fn ($record) => $record->message ?: '—')->columnSpanFull(),
            ])->columns(2),
            SC\Section::make('Urmărire')->schema([
                Forms\Components\Select::make('status')->label('Stare')->options(TenantBookingRequest::STATUSES)->required(),
                Forms\Components\Textarea::make('notes')->label('Notițe interne')->rows(4)->helperText('Le vede doar echipa ta.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Primită')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('artist_name')->label('Artist')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Solicitant')->description(fn ($record) => $record->company)->searchable(['name', 'company', 'email']),
                Tables\Columns\TextColumn::make('event_date')->label('Data evenimentului')->date('d.m.Y')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('city')->label('Oraș')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Stare')->badge()
                    ->formatStateUsing(fn ($state) => TenantBookingRequest::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['new' => 'warning', 'in_progress' => 'info', 'won' => 'success', 'lost' => 'gray'][$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Stare')->options(TenantBookingRequest::STATUSES),
                Tables\Filters\SelectFilter::make('tenant_artist_id')->label('Artist')->relationship('artist', 'name'),
            ])
            ->actions([
                EditAction::make()->label('Deschide'),
            ])
            ->headerActions([
                Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(function () {
                        $tenantId = auth()->user()->tenant?->id;
                        return response()->streamDownload(function () use ($tenantId) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, ['primita', 'artist', 'nume', 'email', 'telefon', 'companie', 'tip', 'data', 'oras', 'locatie', 'capacitate', 'buget', 'stare', 'mesaj']);
                            TenantBookingRequest::where('tenant_id', $tenantId)->orderBy('id')->chunk(500, function ($rows) use ($out) {
                                foreach ($rows as $r) {
                                    fputcsv($out, [$r->created_at?->format('Y-m-d H:i'), $r->artist_name, $r->name, $r->email, $r->phone, $r->company, $r->event_type,
                                        $r->event_date?->format('Y-m-d'), $r->city, $r->venue, $r->capacity, $r->budget, $r->status, $r->message]);
                                }
                            });
                            fclose($out);
                        }, 'cereri-booking-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookingRequests::route('/'),
            'edit' => Pages\EditBookingRequest::route('/{record}/edit'),
        ];
    }
}
