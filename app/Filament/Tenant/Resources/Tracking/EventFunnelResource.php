<?php

namespace App\Filament\Tenant\Resources\Tracking;

use App\Filament\Tenant\Resources\Tracking\EventFunnelResource\Pages;
use App\Models\FeatureStore\FsEventFunnelHourly;
use App\Models\Event;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class EventFunnelResource extends Resource
{
    protected static ?string $model = FsEventFunnelHourly::class;
    protected static ?string $navigationIcon = 'heroicon-o-funnel';
    protected static ?string $navigationGroup = 'Analytics & Tracking';
    protected static ?string $navigationLabel = 'Event Funnels';
    protected static ?string $modelLabel = 'Event Funnel';
    protected static ?string $pluralModelLabel = 'Event Funnels';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Funnel Overview')
                    ->icon('heroicon-o-funnel')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('event_name')
                            ->label('Event')
                            ->content(function ($record) {
                                $event = Event::find($record->event_entity_id);
                                $title = $event?->title;
                                if (is_array($title)) {
                                    $title = $title['en'] ?? $title[array_key_first($title)] ?? 'Unknown';
                                }
                                return $title ?? 'Event #' . $record->event_entity_id;
                            }),
                        Forms\Components\Placeholder::make('hour')
                            ->label('Time Period')
                            ->content(fn ($record) => $record->hour->format('d M Y H:00')),
                        Forms\Components\Placeholder::make('conversion_rate')
                            ->label('Overall Conversion')
                            ->content(fn ($record) => new HtmlString(
                                '<span class="text-lg font-bold text-primary-600">' .
                                number_format($record->overall_conversion_rate * 100, 2) . '%' .
                                '</span>'
                            )),
                    ]),

                SC\Section::make('Funnel Metrics')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Forms\Components\Placeholder::make('funnel_visualization')
                            ->label('')
                            ->content(function ($record) {
                                return new HtmlString(self::renderFunnelVisualization($record));
                            }),
                    ]),

                SC\Section::make('Timing Metrics')
                    ->icon('heroicon-o-clock')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('avg_time_to_cart')
                            ->label('Avg. Time to Cart')
                            ->content(fn ($record) => self::formatDuration($record->avg_time_to_cart_ms)),
                        Forms\Components\Placeholder::make('avg_time_to_checkout')
                            ->label('Avg. Time to Checkout')
                            ->content(fn ($record) => self::formatDuration($record->avg_time_to_checkout_ms)),
                        Forms\Components\Placeholder::make('avg_checkout_duration')
                            ->label('Avg. Checkout Duration')
                            ->content(fn ($record) => self::formatDuration($record->avg_checkout_duration_ms)),
                    ]),

                SC\Section::make('Revenue')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('revenue_gross')
                            ->label('Gross Revenue')
                            ->content(fn ($record) => number_format($record->revenue_gross, 2) . ' RON'),
                        Forms\Components\Placeholder::make('avg_order_value')
                            ->label('Avg. Order Value')
                            ->content(fn ($record) => number_format($record->avg_order_value, 2) . ' RON'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eventEntity.title')
                    ->label('Event')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state['en'] ?? $state[array_key_first($state)] ?? 'Unknown';
                        }
                        return $state ?? 'Unknown';
                    })
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('hour')
                    ->label('Hour')
                    ->dateTime('M d, H:00')
                    ->sortable(),
                Tables\Columns\TextColumn::make('page_views')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('add_to_carts')
                    ->label('Carts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('checkout_starts')
                    ->label('Checkouts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_completed')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('overall_conversion_rate')
                    ->label('Conv. Rate')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 2) . '%')
                    ->color(fn ($state) => match (true) {
                        $state >= 0.05 => 'success',
                        $state >= 0.02 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('revenue_gross')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_entity_id')
                    ->label('Event')
                    ->relationship('eventEntity', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('hour')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('hour', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('hour', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('has_orders')
                    ->label('Has Orders')
                    ->query(fn (Builder $query) => $query->where('orders_completed', '>', 0)),
            ])
            ->defaultSort('hour', 'desc')
            ->groups([
                Tables\Grouping\Group::make('event_entity_id')
                    ->label('By Event')
                    ->collapsible(),
            ]);
    }

    protected static function renderFunnelVisualization($record): string
    {
        $steps = [
            ['label' => 'Page Views', 'value' => $record->page_views, 'color' => 'bg-blue-500'],
            ['label' => 'Ticket Selections', 'value' => $record->ticket_selections, 'color' => 'bg-indigo-500'],
            ['label' => 'Add to Cart', 'value' => $record->add_to_carts, 'color' => 'bg-purple-500'],
            ['label' => 'Checkout Started', 'value' => $record->checkout_starts, 'color' => 'bg-pink-500'],
            ['label' => 'Payment Attempted', 'value' => $record->payment_attempts, 'color' => 'bg-orange-500'],
            ['label' => 'Orders Completed', 'value' => $record->orders_completed, 'color' => 'bg-green-500'],
        ];

        $maxValue = max(1, $record->page_views);

        $html = '<div class="space-y-3">';

        foreach ($steps as $index => $step) {
            $percentage = ($step['value'] / $maxValue) * 100;
            $dropRate = '';

            if ($index > 0 && $steps[$index - 1]['value'] > 0) {
                $drop = (1 - ($step['value'] / $steps[$index - 1]['value'])) * 100;
                $dropRate = '<span class="text-red-500 text-xs ml-2">-' . number_format($drop, 1) . '%</span>';
            }

            $html .= '<div class="flex items-center gap-4">';
            $html .= '<span class="w-40 text-sm font-medium">' . $step['label'] . '</span>';
            $html .= '<div class="flex-1">';
            $html .= '<div class="h-8 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden relative">';
            $html .= '<div class="h-full ' . $step['color'] . ' rounded-full transition-all duration-500" style="width: ' . $percentage . '%"></div>';
            $html .= '<span class="absolute inset-0 flex items-center justify-center text-sm font-medium">' . number_format($step['value']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<span class="w-20 text-right text-sm text-gray-500">' . number_format($percentage, 1) . '%' . $dropRate . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    protected static function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return 'N/A';
        }

        $seconds = $ms / 1000;

        if ($seconds < 60) {
            return number_format($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $minutes . 'm ' . number_format($remainingSeconds, 0) . 's';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventFunnels::route('/'),
            'view' => Pages\ViewEventFunnel::route('/{record}'),
        ];
    }
}
