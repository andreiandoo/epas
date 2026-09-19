<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\GamificationConfigResource\Pages;
use App\Models\Gamification\GamificationConfig;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class GamificationConfigResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = GamificationConfig::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Gamification Settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 46;

    protected static ?string $modelLabel = 'Gamification Settings';

    protected static ?string $pluralModelLabel = 'Gamification Settings';

    protected static ?string $slug = 'gamification-settings';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('gamification');
    }

    /**
     * The explanation of a field, as an info icon right after its label (tooltip on hover) instead of a line of text
     * under the input.
     */
    protected static function info(string $text): SC\Icon
    {
        return SC\Icon::make('heroicon-o-information-circle')
            ->tooltip($text)
            ->color('gray')
            ->extraAttributes(['style' => 'cursor:help']);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make('Recompense automate')
                    ->description('Când e pornit, punctele funcționează singure: se câștigă la fiecare comandă plătită (creditate după activitate), de ziua de naștere și la invitații, se folosesc la checkout și expiră. Reducerea din puncte e plătită de marketplace, nu de organizator.')
                    ->schema([
                        Forms\Components\Toggle::make('auto_rewards_enabled')
                            ->label('Pornește recompensele automate')
                            ->default(false),

                        Forms\Components\Placeholder::make('loyalty_rule_preview')
                            ->label('Cum arată pentru client')
                            ->content(function (callable $get) {
                                $value = (float) ($get('point_value') ?: 0.01);
                                $pct = (float) ($get('earn_percentage') ?: 0);
                                $points = $value > 0 ? (int) floor(round(100 * $pct / 100 / $value, 6)) : 0;
                                $redeem = (float) ($get('max_redeem_percentage') ?: 0);
                                return new \Illuminate\Support\HtmlString(
                                    'La o comandă de <b>100 lei</b> clientul câștigă <b>' . $points . ' puncte</b> (' . number_format($points * $value, 2, ',', '.') . ' lei), adică ' . rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',') . '% înapoi. '
                                    . '1 punct = ' . number_format($value, 2, ',', '.') . ' lei. La plată poate folosi puncte pentru cel mult ' . rtrim(rtrim(number_format($redeem, 2, ',', '.'), '0'), ',') . '% din valoarea biletelor.'
                                );
                            }),

                        Forms\Components\Placeholder::make('loyalty_report')
                            ->label('Costul programului')
                            ->visible(fn ($record) => $record !== null && $record->marketplace_client_id)
                            ->content(function ($record) {
                                if (!$record) {
                                    return '';
                                }
                                $service = app(\App\Services\Gamification\MarketplaceLoyaltyService::class);
                                $rows = [
                                    'Luna aceasta' => $service->report($record, now()->startOfMonth(), now()),
                                    'Luna trecută' => $service->report($record, now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()),
                                ];
                                $lei = fn ($v) => number_format((float) $v, 2, ',', '.') . ' lei';
                                $html = '<table style="width:100%;font-size:13px;border-collapse:collapse"><thead><tr style="text-align:left">'
                                    . '<th style="padding:4px 8px">Perioadă</th><th style="padding:4px 8px">Vânzări bilete</th><th style="padding:4px 8px">Comision</th>'
                                    . '<th style="padding:4px 8px">Puncte folosite</th><th style="padding:4px 8px">Din comision</th><th style="padding:4px 8px">La 100 lei vânzări</th>'
                                    . '<th style="padding:4px 8px">Puncte acordate</th><th style="padding:4px 8px">Expirate</th></tr></thead><tbody>';
                                foreach ($rows as $label => $r) {
                                    $hot = $r['cost_share'] > 25 ? 'color:#b91c1c;font-weight:700' : '';
                                    $html .= '<tr><td style="padding:4px 8px">' . $label . '</td><td style="padding:4px 8px">' . $lei($r['sales_lei']) . '</td><td style="padding:4px 8px">' . $lei($r['commission_lei']) . '</td>'
                                        . '<td style="padding:4px 8px">' . $lei($r['redeemed_lei']) . '</td><td style="padding:4px 8px;' . $hot . '">' . number_format($r['cost_share'], 1, ',', '.') . '%</td>'
                                        . '<td style="padding:4px 8px">' . $lei($r['cost_per_100_lei']) . '</td><td style="padding:4px 8px">' . number_format($r['issued_points'], 0, ',', '.') . ' (' . $lei($r['issued_lei']) . ')</td>'
                                        . '<td style="padding:4px 8px">' . number_format($r['expired_points'], 0, ',', '.') . '</td></tr>';
                                }
                                $now = $rows['Luna aceasta'];
                                $html .= '</tbody></table><p style="margin-top:8px;font-size:13px">În conturi acum: <b>' . number_format($now['outstanding_points'], 0, ',', '.') . ' puncte</b> (' . $lei($now['outstanding_lei']) . ' de acoperit dacă se folosesc toate), plus <b>' . number_format($now['pending_points'], 0, ',', '.') . '</b> în așteptarea activității. Prag de alarmă: peste 25% din comision (0,50 lei la 100 lei vânzări).</p>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Point Value Configuration')
                    ->description('Configure how points are valued and earned')
                    ->schema([
                        Forms\Components\TextInput::make('point_value')
                            ->label('Point Value')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.01)
                            ->live(onBlur: true)
                            ->required()
                            ->afterLabel(static::info('How much is 1 point worth for redemption (e.g., 0.01 = 1 point = 0.01 RON)')),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                                'GBP' => 'GBP',
                            ])
                            ->default('RON')
                            ->required(),

                        Forms\Components\TextInput::make('earn_percentage')
                            ->label('Earn Percentage')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->suffix('%')
                            ->default(5.00)
                            ->live(onBlur: true)
                            ->afterLabel(static::info('Procentul din valoarea biletelor dat înapoi în puncte. Ex.: 0,5 = la 100 lei, 50 de puncte (0,50 lei) când 1 punct = 0,01 lei.')),

                        Forms\Components\Toggle::make('earn_on_subtotal')
                            ->label('Earn on Subtotal')
                            ->default(true)
                            ->afterLabel(static::info('Calculate points based on subtotal (vs total with fees)')),

                        Forms\Components\TextInput::make('min_order_for_earning')
                            ->label('Minimum Order')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->afterLabel(static::info('Minimum order value to earn points (e.g., 10.00)')),

                        Forms\Components\TextInput::make('points_confirm_days')
                            ->label('Zile până la creditare')
                            ->numeric()
                            ->minValue(0)
                            ->default(2)
                            ->afterLabel(static::info('Punctele unei comenzi intră în cont la atâtea zile după activitate (până atunci sunt „în așteptare”; un retur le anulează).')),
                    ])->columns(3),

                SC\Section::make('Redemption Settings')
                    ->description('Configure how points can be redeemed')
                    ->schema([
                        Forms\Components\TextInput::make('min_redeem_points')
                            ->label('Minimum Points to Redeem')
                            ->numeric()
                            ->default(100)
                            ->required(),

                        Forms\Components\TextInput::make('max_redeem_percentage')
                            ->label('Max Redemption (%)')
                            ->numeric()
                            ->step(0.01)
                            ->live(onBlur: true)
                            ->suffix('%')
                            ->default(50.00)
                            ->afterLabel(static::info('Maximum percentage of order that can be paid with points')),

                        Forms\Components\TextInput::make('max_redeem_points_per_order')
                            ->label('Max Points Per Order')
                            ->numeric()
                            ->nullable()
                            ->afterLabel(static::info('Leave empty for no limit')),
                    ])->columns(3),

                SC\Section::make('Bonus Points')
                    ->description('Configure bonus points for special actions')
                    ->schema([
                        Forms\Components\TextInput::make('birthday_bonus_points')
                            ->label('Birthday Bonus')
                            ->numeric()
                            ->default(100),

                        Forms\Components\TextInput::make('signup_bonus_points')
                            ->label('Signup Bonus')
                            ->numeric()
                            ->default(50)
                            ->afterLabel(static::info('Nu se acordă de recompensele automate (un cont nou nu primește puncte înainte să cumpere).')),

                        Forms\Components\TextInput::make('referral_bonus_points')
                            ->label('Referral Bonus (Referrer)')
                            ->numeric()
                            ->default(200)
                            ->afterLabel(static::info('Points awarded to the person who refers')),

                        Forms\Components\TextInput::make('referred_bonus_points')
                            ->label('Referral Bonus (Referred)')
                            ->numeric()
                            ->default(100)
                            ->afterLabel(static::info('Points awarded to the new customer')),

                        Forms\Components\TextInput::make('referral_min_order')
                            ->label('Comandă minimă pentru invitație (lei)')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->afterLabel(static::info('Bonusurile de invitație se acordă când prima comandă a prietenului, de cel puțin atât, e confirmată (în 30 de zile de la înscriere).')),

                        Forms\Components\TextInput::make('referral_max_per_year')
                            ->label('Invitații răsplătite pe an')
                            ->numeric()
                            ->minValue(0)
                            ->default(10)
                            ->afterLabel(static::info('Câți prieteni pot aduce bonus unui client într-un an. 0 = fără limită.')),
                    ])->columns(3),

                SC\Section::make('Expiration Settings')
                    ->schema([
                        Forms\Components\TextInput::make('points_expire_days')
                            ->label('Points Expire After (days)')
                            ->numeric()
                            ->nullable()
                            ->afterLabel(static::info('Leave empty if points never expire')),

                        Forms\Components\Toggle::make('expire_on_inactivity')
                            ->label('Expire on Inactivity')
                            ->default(false),

                        Forms\Components\TextInput::make('inactivity_days')
                            ->label('Inactivity Period (days)')
                            ->numeric()
                            ->default(365)
                            ->visible(fn (callable $get) => $get('expire_on_inactivity')),
                    ])->columns(3),

                SC\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\TextInput::make('points_name')
                            ->label('Points Name (plural)')
                            ->default('puncte')
                            ->required(),

                        Forms\Components\TextInput::make('points_name_singular')
                            ->label('Points Name (singular)')
                            ->default('punct')
                            ->required(),

                        Forms\Components\Select::make('icon')
                            ->options([
                                'star' => 'Star',
                                'sparkles' => 'Sparkles',
                                'gift' => 'Gift',
                                'currency-dollar' => 'Dollar',
                                'trophy' => 'Trophy',
                                'heart' => 'Heart',
                            ])
                            ->default('star'),
                    ])->columns(3),

                SC\Section::make('Customer Tiers')
                    ->description('Define customer loyalty tiers (optional)')
                    ->schema([
                        Forms\Components\Repeater::make('tiers')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Tier Name')
                                    ->required(),
                                Forms\Components\TextInput::make('min_points')
                                    ->label('Minimum Points')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('multiplier')
                                    ->label('Points Multiplier')
                                    ->numeric()
                                    ->default(1.0)
                                    ->afterLabel(static::info('e.g., 1.5 for 50% bonus')),
                                Forms\Components\TextInput::make('color')
                                    ->label('Badge Color')
                                    ->default('#6366f1'),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('earn_percentage')
                    ->label('Earn %')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('point_value')
                    ->label('Point Value')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . ($record->currency ?? 'RON')),

                Tables\Columns\TextColumn::make('min_redeem_points')
                    ->label('Min Redeem'),

                Tables\Columns\TextColumn::make('max_redeem_percentage')
                    ->label('Max Redeem %')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('birthday_bonus_points')
                    ->label('Birthday'),

                Tables\Columns\TextColumn::make('referral_bonus_points')
                    ->label('Referral'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGamificationConfigs::route('/'),
            'create' => Pages\CreateGamificationConfig::route('/create'),
            'edit' => Pages\EditGamificationConfig::route('/{record}/edit'),
        ];
    }
}
