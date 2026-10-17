<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\NewsletterSubscriberResource\Pages;
use App\Models\TenantNewsletterSubscriber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Abonații la newsletterul tenantului: strânși din formularul de pe site și de la plată
 * (bifa „vreau noutăți”). Export CSV, adăugare manuală (cu acordul persoanei), dezabonare.
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = TenantNewsletterSubscriber::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Website';
    protected static ?int $navigationSort = 40;
    protected static ?string $slug = 'newsletter-abonati';
    protected static ?string $modelLabel = 'abonat';
    protected static ?string $pluralModelLabel = 'Abonați newsletter';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Website');
    }

    public static function getNavigationLabel(): string
    {
        return 'Abonați newsletter';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()->tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::fields())->columns(1);
    }

    /** Câmpurile pentru adăugarea manuală a unui abonat. */
    public static function fields(): array
    {
        return [
            Forms\Components\TextInput::make('email')->label('Email')->email()->required()->maxLength(190),
            Forms\Components\TextInput::make('first_name')->label('Prenume')->maxLength(100),
            Forms\Components\Select::make('locale')->label('Limba emailurilor')->options(['ro' => 'Română', 'en' => 'Engleză'])->default('ro')->required(),
            Forms\Components\Checkbox::make('consent')->label('Persoana și-a dat acordul să primească newsletterul')->accepted()->dehydrated(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('subscribed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('first_name')->label('Prenume')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('locale')->label('Limba')->badge(),
                Tables\Columns\TextColumn::make('source')->label('Sursa')->formatStateUsing(fn ($state) => TenantNewsletterSubscriber::SOURCES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->label('Stare')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'subscribed' ? 'Abonat' : 'Dezabonat')
                    ->color(fn ($state) => $state === 'subscribed' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('subscribed_at')->label('Abonat la')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Stare')->options(['subscribed' => 'Abonați', 'unsubscribed' => 'Dezabonați'])->default('subscribed'),
                Tables\Filters\SelectFilter::make('locale')->label('Limba')->options(['ro' => 'Română', 'en' => 'Engleză']),
                Tables\Filters\SelectFilter::make('source')->label('Sursa')->options(TenantNewsletterSubscriber::SOURCES),
            ])
            ->actions([
                Action::make('unsubscribe')->label('Dezabonează')->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible(fn ($record) => $record->status === 'subscribed')->requiresConfirmation()
                    ->action(fn ($record) => $record->unsubscribe()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('unsubscribe')->label('Dezabonează')->icon('heroicon-o-no-symbol')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->unsubscribe()),
                ]),
            ])
            ->headerActions([
                Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(function () {
                        $tenantId = auth()->user()->tenant?->id;
                        return response()->streamDownload(function () use ($tenantId) {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, ['email', 'prenume', 'limba', 'sursa', 'stare', 'abonat_la', 'dezabonat_la']);
                            TenantNewsletterSubscriber::where('tenant_id', $tenantId)->orderBy('id')->chunk(500, function ($rows) use ($out) {
                                foreach ($rows as $r) {
                                    fputcsv($out, [$r->email, $r->first_name, $r->locale, $r->source, $r->status, $r->subscribed_at?->format('Y-m-d H:i'), $r->unsubscribed_at?->format('Y-m-d H:i')]);
                                }
                            });
                            fclose($out);
                        }, 'abonati-newsletter-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
        ];
    }

    /** Adăugarea manuală trece prin aceeași regulă ca formularul de pe site. */
    public static function addManually(array $data): void
    {
        $tenant = auth()->user()->tenant;
        TenantNewsletterSubscriber::subscribe($tenant, (string) $data['email'], [
            'first_name' => $data['first_name'] ?? null, 'locale' => $data['locale'] ?? 'ro', 'source' => 'panel',
        ]);
        Notification::make()->success()->title('Abonat adăugat')->send();
    }
}
