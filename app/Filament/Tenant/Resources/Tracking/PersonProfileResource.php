<?php

namespace App\Filament\Tenant\Resources\Tracking;

use App\Filament\Tenant\Resources\Tracking\PersonProfileResource\Pages;
use App\Models\Platform\CoreCustomer;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\FeatureStore\FsPersonTicketPref;
use App\Models\FeatureStore\FsPersonDaily;
use App\Models\Tracking\TxEvent;
use App\Models\Tracking\TxIdentityLink;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PersonProfileResource extends Resource
{
    protected static ?string $model = CoreCustomer::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Analytics & Tracking';
    protected static ?string $navigationLabel = 'Person Profiles';
    protected static ?string $modelLabel = 'Person Profile';
    protected static ?string $pluralModelLabel = 'Person Profiles';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        // CoreCustomer uses tenant_ids as JSON array
        return parent::getEloquentQuery()->whereJsonContains('tenant_ids', $tenant?->id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Person Details')
                    ->icon('heroicon-o-user-circle')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('id')
                            ->label('Person ID')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold text-primary-600">#' . $record->id . '</span>')),
                        Forms\Components\Placeholder::make('email')
                            ->label('Email')
                            ->content(fn ($record) => new HtmlString('<a href="mailto:' . $record->email . '" class="text-primary-600 hover:underline">' . $record->email . '</a>')),
                        Forms\Components\Placeholder::make('name')
                            ->label('Name')
                            ->content(fn ($record) => trim($record->first_name . ' ' . $record->last_name) ?: 'N/A'),
                        Forms\Components\Placeholder::make('phone')
                            ->label('Phone')
                            ->content(fn ($record) => $record->phone ?? 'N/A'),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Customer Since')
                            ->content(fn ($record) => $record->created_at?->format('d M Y')),
                        Forms\Components\Placeholder::make('identity_links')
                            ->label('Linked Visitors')
                            ->content(fn ($record) => TxIdentityLink::where('person_id', $record->id)->count() . ' visitors'),
                    ]),

                SC\Section::make('Activity Summary')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('total_events')
                            ->label('Total Events')
                            ->content(fn ($record) => number_format(TxEvent::where('person_id', $record->id)->count())),
                        Forms\Components\Placeholder::make('total_orders')
                            ->label('Orders')
                            ->content(fn ($record) => number_format(TxEvent::where('person_id', $record->id)->where('event_name', 'order_completed')->count())),
                        Forms\Components\Placeholder::make('last_seen')
                            ->label('Last Seen')
                            ->content(fn ($record) => TxEvent::where('person_id', $record->id)->max('occurred_at')?->diffForHumans() ?? 'Never'),
                        Forms\Components\Placeholder::make('total_revenue')
                            ->label('Total Spent')
                            ->content(function ($record) {
                                $total = TxEvent::where('person_id', $record->id)
                                    ->where('event_name', 'order_completed')
                                    ->sum('payload->gross_amount');
                                return number_format($total, 2) . ' RON';
                            }),
                    ]),

                SC\Section::make('Artist Affinities')
                    ->icon('heroicon-o-musical-note')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('artist_affinities')
                            ->label('')
                            ->content(function ($record) {
                                $affinities = FsPersonAffinityArtist::where('person_id', $record->id)
                                    ->with('artist:id,name')
                                    ->orderByDesc('affinity_score')
                                    ->limit(10)
                                    ->get();

                                if ($affinities->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 italic">No artist affinities calculated yet</p>');
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($affinities as $aff) {
                                    $percentage = min(100, $aff->affinity_score * 5);
                                    $html .= '<div class="flex items-center gap-3">';
                                    $html .= '<span class="w-40 truncate font-medium">' . ($aff->artist?->name ?? 'Unknown') . '</span>';
                                    $html .= '<div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">';
                                    $html .= '<div class="h-full bg-primary-500 rounded-full" style="width: ' . $percentage . '%"></div>';
                                    $html .= '</div>';
                                    $html .= '<span class="w-16 text-right text-sm text-gray-600">' . number_format($aff->affinity_score, 1) . '</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                SC\Section::make('Genre Preferences')
                    ->icon('heroicon-o-tag')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('genre_affinities')
                            ->label('')
                            ->content(function ($record) {
                                $affinities = FsPersonAffinityGenre::where('person_id', $record->id)
                                    ->orderByDesc('affinity_score')
                                    ->limit(10)
                                    ->get();

                                if ($affinities->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 italic">No genre preferences calculated yet</p>');
                                }

                                $html = '<div class="flex flex-wrap gap-2">';
                                foreach ($affinities as $aff) {
                                    $color = match (true) {
                                        $aff->affinity_score >= 15 => 'bg-primary-500 text-white',
                                        $aff->affinity_score >= 10 => 'bg-primary-200 text-primary-800',
                                        $aff->affinity_score >= 5 => 'bg-primary-100 text-primary-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                    $html .= '<span class="px-3 py-1 rounded-full text-sm font-medium ' . $color . '">';
                                    $html .= $aff->genre . ' (' . number_format($aff->affinity_score, 1) . ')';
                                    $html .= '</span>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                SC\Section::make('Ticket Preferences')
                    ->icon('heroicon-o-ticket')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('ticket_prefs')
                            ->label('Preferred Categories')
                            ->content(function ($record) {
                                $prefs = FsPersonTicketPref::where('person_id', $record->id)
                                    ->where('ticket_category', '!=', '_overall')
                                    ->orderByDesc('preference_score')
                                    ->limit(5)
                                    ->get();

                                if ($prefs->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 italic">No ticket preferences yet</p>');
                                }

                                $html = '<ul class="space-y-1">';
                                foreach ($prefs as $pref) {
                                    $html .= '<li class="flex justify-between">';
                                    $html .= '<span>' . $pref->ticket_category . '</span>';
                                    $html .= '<span class="text-gray-500">' . $pref->purchases_count . ' purchases</span>';
                                    $html .= '</li>';
                                }
                                $html .= '</ul>';

                                return new HtmlString($html);
                            }),
                        Forms\Components\Placeholder::make('price_sensitivity')
                            ->label('Price Sensitivity')
                            ->content(function ($record) {
                                $overall = FsPersonTicketPref::where('person_id', $record->id)
                                    ->where('ticket_category', '_overall')
                                    ->first();

                                if (!$overall) {
                                    return new HtmlString('<p class="text-gray-500 italic">No pricing data yet</p>');
                                }

                                $bandColors = [
                                    'low' => 'text-green-600',
                                    'mid' => 'text-blue-600',
                                    'high' => 'text-orange-600',
                                    'premium' => 'text-purple-600',
                                ];

                                $html = '<div class="space-y-2">';
                                $html .= '<div class="flex justify-between"><span>Avg. Ticket Price:</span><span class="font-medium">' . number_format($overall->avg_price, 2) . ' RON</span></div>';
                                $html .= '<div class="flex justify-between"><span>Price Band:</span><span class="font-medium ' . ($bandColors[$overall->price_band] ?? '') . '">' . ucfirst($overall->price_band) . '</span></div>';
                                $html .= '<div class="flex justify-between"><span>Total Purchases:</span><span class="font-medium">' . $overall->purchases_count . '</span></div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                SC\Section::make('Recent Activity')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('recent_events')
                            ->label('')
                            ->content(function ($record) {
                                $events = TxEvent::where('person_id', $record->id)
                                    ->orderByDesc('occurred_at')
                                    ->limit(20)
                                    ->get(['event_name', 'occurred_at', 'source_system', 'entities']);

                                if ($events->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 italic">No activity recorded</p>');
                                }

                                $html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $html .= '<thead><tr><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Event</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Source</th><th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Details</th></tr></thead>';
                                $html .= '<tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

                                foreach ($events as $event) {
                                    $details = '';
                                    if (!empty($event->entities['event_entity_id'])) {
                                        $details = 'Event #' . $event->entities['event_entity_id'];
                                    } elseif (!empty($event->entities['order_id'])) {
                                        $details = 'Order #' . $event->entities['order_id'];
                                    }

                                    $html .= '<tr>';
                                    $html .= '<td class="px-3 py-2 text-sm text-gray-600">' . $event->occurred_at->format('M d, H:i') . '</td>';
                                    $html .= '<td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">' . $event->event_name . '</span></td>';
                                    $html .= '<td class="px-3 py-2 text-sm">' . ucfirst($event->source_system) . '</td>';
                                    $html .= '<td class="px-3 py-2 text-sm text-gray-500">' . $details . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';

                                return new HtmlString($html);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => trim($record->first_name . ' ' . $record->last_name) ?: 'N/A')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('txEvents')
                    ->sortable(),
                Tables\Columns\TextColumn::make('identity_links_count')
                    ->label('Visitors')
                    ->counts('identityLinks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Since')
                    ->date('M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_events')
                    ->label('Has Tracking Data')
                    ->query(fn (Builder $query) => $query->has('txEvents')),
                Tables\Filters\Filter::make('has_purchases')
                    ->label('Has Purchases')
                    ->query(fn (Builder $query) => $query->whereHas('txEvents', fn ($q) => $q->where('event_name', 'order_completed'))),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonProfiles::route('/'),
            'view' => Pages\ViewPersonProfile::route('/{record}'),
        ];
    }
}
