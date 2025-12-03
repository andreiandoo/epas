<?php

namespace App\Filament\Resources\AudienceTargeting;

use App\Filament\Resources\AudienceTargeting\AudienceSegmentResource\Pages;
use App\Models\AudienceSegment;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AudienceSegmentResource extends Resource
{
    protected static ?string $model = AudienceSegment::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Audience Segments';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Segment Details')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->options(Tenant::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2),

                        Forms\Components\Select::make('segment_type')
                            ->options([
                                'dynamic' => 'Dynamic (Rule-based)',
                                'static' => 'Static (Manual)',
                                'lookalike' => 'Lookalike',
                            ])
                            ->default('dynamic')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'archived' => 'Archived',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Segment Rules')
                    ->schema([
                        Forms\Components\Select::make('criteria.match')
                            ->label('Match Mode')
                            ->options([
                                'all' => 'Match ALL rules (AND)',
                                'any' => 'Match ANY rule (OR)',
                            ])
                            ->default('all'),

                        Forms\Components\Repeater::make('criteria.rules')
                            ->label('Rules')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->options([
                                        'total_spent' => 'Total Spent (EUR)',
                                        'purchase_count' => 'Number of Purchases',
                                        'avg_order' => 'Avg Order Value (EUR)',
                                        'engagement_score' => 'Engagement Score (0-100)',
                                        'churn_risk' => 'Churn Risk (0-100)',
                                        'last_purchase' => 'Last Purchase',
                                        'genres' => 'Preferred Genres',
                                        'event_types' => 'Preferred Event Types',
                                        'city' => 'City',
                                        'country' => 'Country',
                                        'age' => 'Age',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Forms\Components\Select::make('operator')
                                    ->options(fn (callable $get) => match ($get('field')) {
                                        'total_spent', 'purchase_count', 'avg_order', 'engagement_score', 'churn_risk', 'age' => [
                                            '>=' => 'Greater than or equal',
                                            '<=' => 'Less than or equal',
                                            '=' => 'Equals',
                                            'between' => 'Between',
                                        ],
                                        'last_purchase' => [
                                            'within_days' => 'Within last X days',
                                            'before_days' => 'More than X days ago',
                                        ],
                                        'genres', 'event_types' => [
                                            'includes' => 'Includes any of',
                                        ],
                                        'city', 'country' => [
                                            'is' => 'Is',
                                            'in' => 'Is one of',
                                        ],
                                        default => ['=' => 'Equals'],
                                    })
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->visible(fn (callable $get) => !in_array($get('field'), ['genres', 'event_types']))
                                    ->helperText(fn (callable $get) => match ($get('field')) {
                                        'last_purchase' => 'Number of days',
                                        'total_spent', 'avg_order' => 'Amount in EUR',
                                        'engagement_score', 'churn_risk' => 'Value 0-100',
                                        default => null,
                                    }),

                                Forms\Components\Select::make('value')
                                    ->label('Genres')
                                    ->multiple()
                                    ->options(EventGenre::pluck('slug', 'slug'))
                                    ->visible(fn (callable $get) => $get('field') === 'genres'),

                                Forms\Components\Select::make('value')
                                    ->label('Event Types')
                                    ->multiple()
                                    ->options(EventType::pluck('slug', 'slug'))
                                    ->visible(fn (callable $get) => $get('field') === 'event_types'),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['field'] ?? null),
                    ])
                    ->visible(fn (callable $get) => $get('segment_type') === 'dynamic'),

                Forms\Components\Section::make('Auto-Refresh Settings')
                    ->schema([
                        Forms\Components\Toggle::make('auto_refresh')
                            ->label('Enable Auto-Refresh')
                            ->default(true),

                        Forms\Components\TextInput::make('refresh_interval_hours')
                            ->label('Refresh Interval (hours)')
                            ->numeric()
                            ->default(24)
                            ->minValue(1)
                            ->maxValue(168),
                    ])
                    ->columns(2)
                    ->visible(fn (callable $get) => $get('segment_type') === 'dynamic'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('segment_type')
                    ->colors([
                        'primary' => 'dynamic',
                        'success' => 'static',
                        'warning' => 'lookalike',
                    ]),

                Tables\Columns\TextColumn::make('customer_count')
                    ->label('Customers')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'archived',
                    ]),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Synced')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(Tenant::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('segment_type')
                    ->options([
                        'dynamic' => 'Dynamic',
                        'static' => 'Static',
                        'lookalike' => 'Lookalike',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (AudienceSegment $record) {
                        app(\App\Services\AudienceTargeting\SegmentationService::class)
                            ->refreshSegment($record);
                    })
                    ->visible(fn (AudienceSegment $record) => $record->segment_type === 'dynamic'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudienceSegments::route('/'),
            'create' => Pages\CreateAudienceSegment::route('/create'),
            'edit' => Pages\EditAudienceSegment::route('/{record}/edit'),
        ];
    }
}
