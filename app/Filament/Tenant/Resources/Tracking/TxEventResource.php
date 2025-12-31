<?php

namespace App\Filament\Tenant\Resources\Tracking;

use App\Filament\Tenant\Resources\Tracking\TxEventResource\Pages;
use App\Models\Tracking\TxEvent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TxEventResource extends Resource
{
    protected static ?string $model = TxEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Analytics & Tracking';
    protected static ?string $navigationLabel = 'Events Stream';
    protected static ?string $modelLabel = 'Tracking Event';
    protected static ?string $pluralModelLabel = 'Tracking Events';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Event Details')
                    ->icon('heroicon-o-signal')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('event_id')
                            ->label('Event ID')
                            ->content(fn ($record) => new HtmlString('<code class="text-xs">' . $record->event_id . '</code>')),
                        Forms\Components\Placeholder::make('event_name')
                            ->label('Event Name')
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium bg-primary-100 text-primary-700">' . $record->event_name . '</span>')),
                        Forms\Components\Placeholder::make('source_system')
                            ->label('Source')
                            ->content(fn ($record) => ucfirst($record->source_system)),
                        Forms\Components\Placeholder::make('occurred_at')
                            ->label('Occurred At')
                            ->content(fn ($record) => $record->occurred_at?->format('d M Y H:i:s')),
                        Forms\Components\Placeholder::make('received_at')
                            ->label('Received At')
                            ->content(fn ($record) => $record->received_at?->format('d M Y H:i:s')),
                        Forms\Components\Placeholder::make('event_version')
                            ->label('Version')
                            ->content(fn ($record) => 'v' . $record->event_version),
                    ]),

                SC\Section::make('Identity')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('visitor_id')
                            ->label('Visitor ID')
                            ->content(fn ($record) => $record->visitor_id
                                ? new HtmlString('<code class="text-xs">' . $record->visitor_id . '</code>')
                                : 'N/A'),
                        Forms\Components\Placeholder::make('session_id')
                            ->label('Session ID')
                            ->content(fn ($record) => $record->session_id
                                ? new HtmlString('<code class="text-xs">' . $record->session_id . '</code>')
                                : 'N/A'),
                        Forms\Components\Placeholder::make('person_id')
                            ->label('Person ID')
                            ->content(fn ($record) => $record->person_id
                                ? new HtmlString('<a href="#" class="text-primary-600 hover:underline">#' . $record->person_id . '</a>')
                                : new HtmlString('<span class="text-gray-400">Anonymous</span>')),
                        Forms\Components\Placeholder::make('sequence_no')
                            ->label('Sequence #')
                            ->content(fn ($record) => $record->sequence_no ?? 'N/A'),
                    ]),

                SC\Section::make('Consent')
                    ->icon('heroicon-o-shield-check')
                    ->columns(4)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('consent_necessary')
                            ->label('Necessary')
                            ->content(fn ($record) => self::consentBadge($record->consent_snapshot['necessary'] ?? false)),
                        Forms\Components\Placeholder::make('consent_analytics')
                            ->label('Analytics')
                            ->content(fn ($record) => self::consentBadge($record->consent_snapshot['analytics'] ?? false)),
                        Forms\Components\Placeholder::make('consent_marketing')
                            ->label('Marketing')
                            ->content(fn ($record) => self::consentBadge($record->consent_snapshot['marketing'] ?? false)),
                        Forms\Components\Placeholder::make('consent_data_processing')
                            ->label('Data Processing')
                            ->content(fn ($record) => self::consentBadge($record->consent_snapshot['data_processing'] ?? false)),
                    ]),

                SC\Section::make('Context')
                    ->icon('heroicon-o-globe-alt')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('context_json')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                '<pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto">' .
                                json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
                                '</pre>'
                            )),
                    ]),

                SC\Section::make('Entities')
                    ->icon('heroicon-o-cube')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->entities))
                    ->schema([
                        Forms\Components\Placeholder::make('entities_json')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                '<pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto">' .
                                json_encode($record->entities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
                                '</pre>'
                            )),
                    ]),

                SC\Section::make('Payload')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->payload))
                    ->schema([
                        Forms\Components\Placeholder::make('payload_json')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                '<pre class="bg-gray-50 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto">' .
                                json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
                                '</pre>'
                            )),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime('d M H:i:s')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('event_name')
                    ->label('Event')
                    ->colors([
                        'primary' => fn ($state) => in_array($state, ['page_view', 'event_view']),
                        'warning' => fn ($state) => in_array($state, ['add_to_cart', 'checkout_started']),
                        'success' => fn ($state) => in_array($state, ['order_completed', 'payment_succeeded']),
                        'danger' => fn ($state) => in_array($state, ['payment_failed', 'checkout_abandoned']),
                        'gray' => true,
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_system')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('visitor_id')
                    ->label('Visitor')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->visitor_id)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('person_id')
                    ->label('Identified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => $record->person_id !== null),
                Tables\Columns\TextColumn::make('entities.event_entity_id')
                    ->label('Event')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('entities.order_id')
                    ->label('Order')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('context.page.url')
                    ->label('Page')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->context['page']['url'] ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_name')
                    ->label('Event Type')
                    ->multiple()
                    ->options([
                        'page_view' => 'Page View',
                        'event_view' => 'Event View',
                        'ticket_type_selected' => 'Ticket Selected',
                        'add_to_cart' => 'Add to Cart',
                        'checkout_started' => 'Checkout Started',
                        'payment_attempted' => 'Payment Attempted',
                        'order_completed' => 'Order Completed',
                        'payment_succeeded' => 'Payment Succeeded',
                        'payment_failed' => 'Payment Failed',
                        'entry_granted' => 'Entry Granted',
                    ]),
                Tables\Filters\SelectFilter::make('source_system')
                    ->label('Source')
                    ->options([
                        'web' => 'Web',
                        'mobile' => 'Mobile',
                        'scanner' => 'Scanner',
                        'backend' => 'Backend',
                        'payments' => 'Payments',
                    ]),
                Tables\Filters\TernaryFilter::make('person_id')
                    ->label('Identified')
                    ->placeholder('All')
                    ->trueLabel('Identified only')
                    ->falseLabel('Anonymous only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('person_id'),
                        false: fn (Builder $query) => $query->whereNull('person_id'),
                    ),
                Tables\Filters\Filter::make('occurred_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->poll('10s');
    }

    protected static function consentBadge(bool $granted): HtmlString
    {
        if ($granted) {
            return new HtmlString('<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-success-100 text-success-700">✓ Granted</span>');
        }
        return new HtmlString('<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-500">✗ Denied</span>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTxEvents::route('/'),
            'view' => Pages\ViewTxEvent::route('/{record}'),
        ];
    }
}
