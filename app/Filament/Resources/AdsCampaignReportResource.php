<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdsCampaignReportResource\Pages;
use App\Models\AdsCampaign\AdsCampaignReport;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\ViewAction;

class AdsCampaignReportResource extends Resource
{
    protected static ?string $model = AdsCampaignReport::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Campaign Reports';

    protected static \UnitEnum|string|null $navigationGroup = 'Ads Manager';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Campaign Report';

    protected static ?string $slug = 'ads-reports';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Report Overview')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required(),
                        Forms\Components\Select::make('campaign_id')
                            ->relationship('campaign', 'name')
                            ->required(),
                        Forms\Components\Select::make('report_type')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'final' => 'Final',
                                'ab_test' => 'A/B Test Results',
                                'custom' => 'Custom',
                            ]),
                        Forms\Components\DatePicker::make('period_start'),
                        Forms\Components\DatePicker::make('period_end'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'daily' => 'gray',
                        'weekly' => 'info',
                        'monthly' => 'primary',
                        'final' => 'success',
                        'ab_test' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('period_start')
                    ->date('M d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date('M d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('summary.spend')
                    ->label('Spend')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . ' EUR' : '-'),

                Tables\Columns\TextColumn::make('summary.revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . ' EUR' : '-'),

                Tables\Columns\TextColumn::make('summary.roas')
                    ->label('ROAS')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . 'x' : '-')
                    ->color(fn ($state) => (float)$state >= 2 ? 'success' : ((float)$state >= 1 ? 'warning' : 'danger')),

                Tables\Columns\TextColumn::make('summary.conversions')
                    ->label('Conv.')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int)$state) : '-'),

                Tables\Columns\IconColumn::make('sent_to_organizer')
                    ->label('Sent')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'final' => 'Final',
                    ]),
                Tables\Filters\Filter::make('unsent')
                    ->label('Not Sent to Organizer')
                    ->query(fn ($query) => $query->where('sent_to_organizer', false)),
            ])
            ->recordActions([
                ViewAction::make(),
                Actions\Action::make('send_to_organizer')
                    ->label('Send to Organizer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn ($record) => !$record->sent_to_organizer)
                    ->requiresConfirmation()
                    ->action(function (AdsCampaignReport $record) {
                        $record->markSent();
                        \Filament\Notifications\Notification::make()->success()->title('Report marked as sent')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdsCampaignReports::route('/'),
        ];
    }
}
