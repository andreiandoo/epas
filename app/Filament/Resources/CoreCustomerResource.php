<?php

namespace App\Filament\Resources;

use App\Models\Platform\CoreCustomer;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoreCustomerResource extends Resource
{
    protected static ?string $model = CoreCustomer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customers';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();
        return $count > 0 ? number_format($count) : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->disabled(),

                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tags & Notes')
                    ->schema([
                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->limit(8)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('email_display')
                    ->label('Email')
                    ->getStateUsing(fn ($record) => $record->email_hash ? substr($record->email_hash, 0, 8) . '...' : 'N/A')
                    ->description(fn ($record) => $record->full_name)
                    ->searchable(query: fn ($query, $search) => $query->where('email_hash', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('customer_segment')
                    ->label('Segment')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'VIP' => 'success',
                        'Champions' => 'success',
                        'Loyal' => 'info',
                        'Repeat Buyer' => 'info',
                        'First-Time Buyer' => 'warning',
                        'At Risk' => 'danger',
                        'Lapsed VIP' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rfm_segment')
                    ->label('RFM')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Champions' => 'success',
                        'Loyal' => 'info',
                        'Potential Loyalist' => 'info',
                        'At Risk' => 'danger',
                        'Cannot Lose Them' => 'danger',
                        'Lost' => 'gray',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('first_utm_source')
                    ->label('First Source')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('has_gclid')
                    ->label('Google')
                    ->getStateUsing(fn ($record) => (bool) $record->first_gclid)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('has_fbclid')
                    ->label('FB')
                    ->getStateUsing(fn ($record) => (bool) $record->first_fbclid)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('first_seen_at')
                    ->label('First Seen')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_segment')
                    ->label('Segment')
                    ->options([
                        'New' => 'New',
                        'First-Time Buyer' => 'First-Time Buyer',
                        'Repeat Buyer' => 'Repeat Buyer',
                        'VIP' => 'VIP',
                        'Lapsed VIP' => 'Lapsed VIP',
                        'At Risk' => 'At Risk',
                        'Engaged Non-Buyer' => 'Engaged Non-Buyer',
                    ]),

                Tables\Filters\SelectFilter::make('rfm_segment')
                    ->label('RFM Segment')
                    ->options([
                        'Champions' => 'Champions',
                        'Loyal' => 'Loyal',
                        'Potential Loyalist' => 'Potential Loyalist',
                        'New Customers' => 'New Customers',
                        'Promising' => 'Promising',
                        'Need Attention' => 'Need Attention',
                        'About To Sleep' => 'About To Sleep',
                        'At Risk' => 'At Risk',
                        'Cannot Lose Them' => 'Cannot Lose Them',
                        'Hibernating' => 'Hibernating',
                        'Lost' => 'Lost',
                    ]),

                Tables\Filters\Filter::make('purchasers')
                    ->label('Has Purchased')
                    ->query(fn ($query) => $query->where('total_orders', '>', 0)),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value ($500+)')
                    ->query(fn ($query) => $query->where('total_spent', '>=', 500)),

                Tables\Filters\Filter::make('has_email')
                    ->label('Has Email')
                    ->query(fn ($query) => $query->whereNotNull('email_hash')),

                Tables\Filters\Filter::make('from_google_ads')
                    ->label('From Google Ads')
                    ->query(fn ($query) => $query->whereNotNull('first_gclid')),

                Tables\Filters\Filter::make('from_facebook')
                    ->label('From Facebook')
                    ->query(fn ($query) => $query->whereNotNull('first_fbclid')),

                Tables\Filters\Filter::make('active')
                    ->label('Active (30 days)')
                    ->query(fn ($query) => $query->where('last_seen_at', '>=', now()->subDays(30))),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->label('Edit Tags'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return self::exportCustomers();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('export_selected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($records) {
                        return self::exportCustomers($records);
                    }),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->poll('60s');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Customer Overview')
                    ->schema([
                        SC\Grid::make(4)
                            ->schema([
                                SC\TextEntry::make('customer_segment')
                                    ->label('Segment')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'VIP' => 'success',
                                        'Champions' => 'success',
                                        'At Risk' => 'danger',
                                        default => 'info',
                                    }),

                                SC\TextEntry::make('rfm_segment')
                                    ->label('RFM Segment')
                                    ->badge(),

                                SC\TextEntry::make('total_orders')
                                    ->label('Total Orders'),

                                SC\TextEntry::make('total_spent')
                                    ->label('Total Spent')
                                    ->money('USD'),
                            ]),
                    ]),

                SC\Section::make('Contact Information')
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                SC\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable(),

                                SC\TextEntry::make('phone')
                                    ->label('Phone'),

                                SC\TextEntry::make('full_name')
                                    ->label('Name'),

                                SC\TextEntry::make('country_code')
                                    ->label('Country'),
                            ]),
                    ])
                    ->collapsible(),

                SC\Section::make('Engagement Metrics')
                    ->schema([
                        SC\Grid::make(4)
                            ->schema([
                                SC\TextEntry::make('total_visits')
                                    ->label('Total Visits'),

                                SC\TextEntry::make('total_pageviews')
                                    ->label('Page Views'),

                                SC\TextEntry::make('engagement_score')
                                    ->label('Engagement Score'),

                                SC\TextEntry::make('average_order_value')
                                    ->label('Avg Order Value')
                                    ->money('USD'),
                            ]),
                    ]),

                SC\Section::make('RFM Scores')
                    ->schema([
                        SC\Grid::make(4)
                            ->schema([
                                SC\TextEntry::make('rfm_recency_score')
                                    ->label('Recency')
                                    ->badge()
                                    ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 2 ? 'warning' : 'danger')),

                                SC\TextEntry::make('rfm_frequency_score')
                                    ->label('Frequency')
                                    ->badge()
                                    ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 2 ? 'warning' : 'danger')),

                                SC\TextEntry::make('rfm_monetary_score')
                                    ->label('Monetary')
                                    ->badge()
                                    ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 2 ? 'warning' : 'danger')),

                                SC\TextEntry::make('lifetime_value')
                                    ->label('Lifetime Value')
                                    ->money('USD'),
                            ]),
                    ])
                    ->collapsible(),

                SC\Section::make('Attribution')
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                SC\TextEntry::make('first_utm_source')
                                    ->label('First UTM Source')
                                    ->placeholder('-'),

                                SC\TextEntry::make('first_utm_medium')
                                    ->label('First UTM Medium')
                                    ->placeholder('-'),

                                SC\TextEntry::make('first_utm_campaign')
                                    ->label('First Campaign')
                                    ->placeholder('-'),

                                SC\TextEntry::make('first_referrer')
                                    ->label('First Referrer')
                                    ->placeholder('Direct'),
                            ]),

                        SC\Grid::make(4)
                            ->schema([
                                SC\IconEntry::make('has_gclid')
                                    ->label('Google Ads')
                                    ->getStateUsing(fn ($record) => (bool) $record->first_gclid)
                                    ->boolean(),

                                SC\IconEntry::make('has_fbclid')
                                    ->label('Facebook Ads')
                                    ->getStateUsing(fn ($record) => (bool) $record->first_fbclid)
                                    ->boolean(),

                                SC\IconEntry::make('has_ttclid')
                                    ->label('TikTok Ads')
                                    ->getStateUsing(fn ($record) => (bool) $record->first_ttclid)
                                    ->boolean(),

                                SC\IconEntry::make('has_li_fat_id')
                                    ->label('LinkedIn Ads')
                                    ->getStateUsing(fn ($record) => (bool) $record->first_li_fat_id)
                                    ->boolean(),
                            ]),
                    ])
                    ->collapsible(),

                SC\Section::make('Timeline')
                    ->schema([
                        SC\Grid::make(4)
                            ->schema([
                                SC\TextEntry::make('first_seen_at')
                                    ->label('First Seen')
                                    ->dateTime(),

                                SC\TextEntry::make('last_seen_at')
                                    ->label('Last Seen')
                                    ->dateTime(),

                                SC\TextEntry::make('first_purchase_at')
                                    ->label('First Purchase')
                                    ->dateTime()
                                    ->placeholder('Never'),

                                SC\TextEntry::make('last_purchase_at')
                                    ->label('Last Purchase')
                                    ->dateTime()
                                    ->placeholder('Never'),
                            ]),
                    ])
                    ->collapsible(),

                SC\Section::make('Tags & Notes')
                    ->schema([
                        SC\TextEntry::make('tags')
                            ->label('Tags')
                            ->badge()
                            ->separator(',')
                            ->placeholder('No tags'),

                        SC\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\CoreCustomerResource\RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CoreCustomerResource\Pages\ListCoreCustomers::route('/'),
            'view' => \App\Filament\Resources\CoreCustomerResource\Pages\ViewCoreCustomer::route('/{record}'),
            'edit' => \App\Filament\Resources\CoreCustomerResource\Pages\EditCoreCustomer::route('/{record}/edit'),
        ];
    }

    public static function exportCustomers($records = null): StreamedResponse
    {
        $filename = 'customers_export_' . now()->format('Y-m-d_His') . '.csv';

        return Response::streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($handle, [
                'UUID',
                'Segment',
                'RFM Segment',
                'Total Orders',
                'Total Spent',
                'Avg Order Value',
                'Lifetime Value',
                'Total Visits',
                'Page Views',
                'Engagement Score',
                'First UTM Source',
                'First UTM Medium',
                'First UTM Campaign',
                'Has Google Ads',
                'Has Facebook Ads',
                'Has TikTok Ads',
                'Has LinkedIn Ads',
                'Country',
                'First Seen',
                'Last Seen',
                'First Purchase',
                'Last Purchase',
                'Tags',
            ]);

            $query = $records ?? CoreCustomer::query();

            if ($records === null) {
                $query->orderByDesc('last_seen_at')->chunk(500, function ($customers) use ($handle) {
                    foreach ($customers as $customer) {
                        self::writeCustomerRow($handle, $customer);
                    }
                });
            } else {
                foreach ($records as $customer) {
                    self::writeCustomerRow($handle, $customer);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected static function writeCustomerRow($handle, $customer): void
    {
        fputcsv($handle, [
            $customer->uuid,
            $customer->customer_segment,
            $customer->rfm_segment,
            $customer->total_orders,
            $customer->total_spent,
            $customer->average_order_value,
            $customer->lifetime_value,
            $customer->total_visits,
            $customer->total_pageviews,
            $customer->engagement_score,
            $customer->first_utm_source,
            $customer->first_utm_medium,
            $customer->first_utm_campaign,
            $customer->first_gclid ? 'Yes' : 'No',
            $customer->first_fbclid ? 'Yes' : 'No',
            $customer->first_ttclid ? 'Yes' : 'No',
            $customer->first_li_fat_id ? 'Yes' : 'No',
            $customer->country_code,
            $customer->first_seen_at?->format('Y-m-d H:i:s'),
            $customer->last_seen_at?->format('Y-m-d H:i:s'),
            $customer->first_purchase_at?->format('Y-m-d H:i:s'),
            $customer->last_purchase_at?->format('Y-m-d H:i:s'),
            is_array($customer->tags) ? implode(', ', $customer->tags) : $customer->tags,
        ]);
    }
}
