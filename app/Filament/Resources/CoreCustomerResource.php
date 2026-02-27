<?php

namespace App\Filament\Resources;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
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
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use BackedEnum;
use UnitEnum;

class CoreCustomerResource extends Resource
{
    protected static ?string $model = CoreCustomer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Customers';

    protected static UnitEnum|string|null $navigationGroup = 'Platform Marketing';

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
                // ===== IDENTITY =====
                SC\Section::make('Customer Identity')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\Placeholder::make('display_email')
                            ->label('Email')
                            ->content(fn ($record) => $record?->email ?? '-'),

                        Forms\Components\Placeholder::make('display_name')
                            ->label('Full Name')
                            ->content(fn ($record) => $record?->full_name ?? '-'),

                        Forms\Components\Placeholder::make('display_phone')
                            ->label('Phone')
                            ->content(fn ($record) => $record?->phone ?? '-'),

                        Forms\Components\Placeholder::make('display_uuid')
                            ->label('UUID')
                            ->content(fn ($record) => $record?->uuid ?? '-'),

                        Forms\Components\Placeholder::make('display_country')
                            ->label('Location')
                            ->content(fn ($record) => implode(', ', array_filter([
                                $record?->city,
                                $record?->region,
                                $record?->country_code,
                            ])) ?: '-'),

                        Forms\Components\Placeholder::make('display_language')
                            ->label('Language')
                            ->content(fn ($record) => strtoupper($record?->language ?? '-')),

                        Forms\Components\Placeholder::make('display_gender')
                            ->label('Gender')
                            ->content(fn ($record) => ucfirst($record?->gender ?? '-')),

                        Forms\Components\Placeholder::make('display_age')
                            ->label('Age Range')
                            ->content(fn ($record) => $record?->age_range ?? '-'),
                    ])
                    ->columns(4),

                // ===== SEGMENTATION & SCORING =====
                SC\Section::make('Segmentation & Scoring')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        Forms\Components\Placeholder::make('display_segment')
                            ->label('Customer Segment')
                            ->content(fn ($record) => $record?->customer_segment
                                ? new HtmlString('<span class="fi-badge inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium ' . self::getSegmentClass($record->customer_segment) . '">' . e($record->customer_segment) . '</span>')
                                : '-'),

                        Forms\Components\Placeholder::make('display_rfm_segment')
                            ->label('RFM Segment')
                            ->content(fn ($record) => $record?->rfm_segment
                                ? new HtmlString('<span class="fi-badge inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium ' . self::getRfmClass($record->rfm_segment) . '">' . e($record->rfm_segment) . '</span>')
                                : '-'),

                        Forms\Components\Placeholder::make('display_rfm_scores')
                            ->label('RFM Scores (R / F / M)')
                            ->content(fn ($record) => $record
                                ? ($record->rfm_recency_score ?? 0) . ' / ' . ($record->rfm_frequency_score ?? 0) . ' / ' . ($record->rfm_monetary_score ?? 0) . ' (Total: ' . ($record->rfm_score ?? 0) . ')'
                                : '-'),

                        Forms\Components\Placeholder::make('display_engagement_score')
                            ->label('Engagement Score')
                            ->content(fn ($record) => $record?->engagement_score !== null
                                ? new HtmlString(self::renderScoreBar($record->engagement_score, 'Engagement'))
                                : '-'),

                        Forms\Components\Placeholder::make('display_health_score')
                            ->label('Health Score')
                            ->content(fn ($record) => $record?->health_score !== null
                                ? new HtmlString(self::renderScoreBar($record->health_score, 'Health'))
                                : '-'),

                        Forms\Components\Placeholder::make('display_churn_risk')
                            ->label('Churn Risk')
                            ->content(fn ($record) => $record?->churn_risk_score !== null
                                ? new HtmlString(self::renderScoreBar($record->churn_risk_score, 'Risk', true))
                                : '-'),

                        Forms\Components\Placeholder::make('display_purchase_likelihood')
                            ->label('Purchase Likelihood')
                            ->content(fn ($record) => $record?->purchase_likelihood_score !== null
                                ? new HtmlString(self::renderScoreBar($record->purchase_likelihood_score, 'Likelihood'))
                                : '-'),

                        Forms\Components\Placeholder::make('display_predicted_ltv')
                            ->label('Predicted LTV')
                            ->content(fn ($record) => $record?->predicted_ltv
                                ? number_format((float) $record->predicted_ltv, 2) . ' ' . ($record->currency ?? 'EUR')
                                : '-'),
                    ])
                    ->columns(4),

                // ===== PURCHASE BEHAVIOR =====
                SC\Section::make('Purchase Behavior')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        Forms\Components\Placeholder::make('display_total_orders')
                            ->label('Total Orders')
                            ->content(fn ($record) => $record?->total_orders ?? 0),

                        Forms\Components\Placeholder::make('display_total_tickets')
                            ->label('Total Tickets')
                            ->content(fn ($record) => $record?->total_tickets ?? 0),

                        Forms\Components\Placeholder::make('display_total_spent')
                            ->label('Total Spent')
                            ->content(fn ($record) => $record
                                ? number_format((float) ($record->total_spent ?? 0), 2) . ' ' . ($record->currency ?? 'EUR')
                                : '0.00'),

                        Forms\Components\Placeholder::make('display_aov')
                            ->label('Avg Order Value')
                            ->content(fn ($record) => $record
                                ? number_format((float) ($record->average_order_value ?? 0), 2) . ' ' . ($record->currency ?? 'EUR')
                                : '0.00'),

                        Forms\Components\Placeholder::make('display_ltv')
                            ->label('Lifetime Value')
                            ->content(fn ($record) => $record
                                ? number_format((float) ($record->lifetime_value ?? 0), 2) . ' ' . ($record->currency ?? 'EUR')
                                : '0.00'),

                        Forms\Components\Placeholder::make('display_first_purchase')
                            ->label('First Purchase')
                            ->content(fn ($record) => $record?->first_purchase_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('display_last_purchase')
                            ->label('Last Purchase')
                            ->content(fn ($record) => $record?->last_purchase_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('display_purchase_freq')
                            ->label('Purchase Frequency')
                            ->content(fn ($record) => $record?->purchase_frequency_days
                                ? 'Every ' . $record->purchase_frequency_days . ' days'
                                : '-'),

                        Forms\Components\Placeholder::make('display_days_since')
                            ->label('Days Since Last Purchase')
                            ->content(fn ($record) => $record?->days_since_last_purchase ?? '-'),

                        Forms\Components\Placeholder::make('display_cart_abandoned')
                            ->label('Cart Abandoned')
                            ->content(fn ($record) => $record?->has_cart_abandoned
                                ? new HtmlString('<span class="text-warning-600 font-medium">Yes</span>' . ($record->last_cart_abandoned_at ? ' (' . $record->last_cart_abandoned_at->format('d M Y') . ')' : ''))
                                : 'No'),
                    ])
                    ->columns(5),

                // ===== ENGAGEMENT =====
                SC\Section::make('Engagement Metrics')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('display_first_seen')
                            ->label('First Seen')
                            ->content(fn ($record) => $record?->first_seen_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('display_last_seen')
                            ->label('Last Seen')
                            ->content(fn ($record) => $record?->last_seen_at?->diffForHumans() ?? '-'),

                        Forms\Components\Placeholder::make('display_total_visits')
                            ->label('Total Visits')
                            ->content(fn ($record) => number_format($record?->total_visits ?? 0)),

                        Forms\Components\Placeholder::make('display_total_pageviews')
                            ->label('Total Pageviews')
                            ->content(fn ($record) => number_format($record?->total_pageviews ?? 0)),

                        Forms\Components\Placeholder::make('display_total_sessions')
                            ->label('Total Sessions')
                            ->content(fn ($record) => number_format($record?->total_sessions ?? 0)),

                        Forms\Components\Placeholder::make('display_time_spent')
                            ->label('Total Time Spent')
                            ->content(function ($record) {
                                $seconds = $record?->total_time_spent_seconds ?? 0;
                                if ($seconds < 60) return $seconds . 's';
                                if ($seconds < 3600) return round($seconds / 60) . 'min';
                                return round($seconds / 3600, 1) . 'h';
                            }),

                        Forms\Components\Placeholder::make('display_avg_session')
                            ->label('Avg Session Duration')
                            ->content(function ($record) {
                                $seconds = $record?->avg_session_duration_seconds ?? 0;
                                if ($seconds < 60) return $seconds . 's';
                                return round($seconds / 60, 1) . 'min';
                            }),

                        Forms\Components\Placeholder::make('display_bounce_rate')
                            ->label('Bounce Rate')
                            ->content(fn ($record) => $record?->bounce_rate !== null
                                ? number_format((float) $record->bounce_rate, 1) . '%'
                                : '-'),

                        Forms\Components\Placeholder::make('display_events_viewed')
                            ->label('Events Viewed')
                            ->content(fn ($record) => $record?->total_events_viewed ?? 0),

                        Forms\Components\Placeholder::make('display_events_attended')
                            ->label('Events Attended')
                            ->content(fn ($record) => $record?->total_events_attended ?? 0),
                    ])
                    ->columns(5),

                // ===== ATTRIBUTION =====
                SC\Section::make('Attribution')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->collapsed()
                    ->schema([
                        SC\Fieldset::make('First Touch')
                            ->schema([
                                Forms\Components\Placeholder::make('display_first_source')
                                    ->label('Source')
                                    ->content(fn ($record) => $record?->first_source ?? '-'),

                                Forms\Components\Placeholder::make('display_first_medium')
                                    ->label('Medium')
                                    ->content(fn ($record) => $record?->first_medium ?? '-'),

                                Forms\Components\Placeholder::make('display_first_campaign')
                                    ->label('Campaign')
                                    ->content(fn ($record) => $record?->first_campaign ?? '-'),

                                Forms\Components\Placeholder::make('display_first_referrer')
                                    ->label('Referrer')
                                    ->content(fn ($record) => $record?->first_referrer ?? '-'),

                                Forms\Components\Placeholder::make('display_first_landing')
                                    ->label('Landing Page')
                                    ->content(fn ($record) => $record?->first_landing_page
                                        ? new HtmlString('<span class="text-xs break-all">' . e($record->first_landing_page) . '</span>')
                                        : '-'),

                                Forms\Components\Placeholder::make('display_first_utm')
                                    ->label('UTM (Source / Medium / Campaign)')
                                    ->content(fn ($record) => implode(' / ', array_filter([
                                        $record?->first_utm_source,
                                        $record?->first_utm_medium,
                                        $record?->first_utm_campaign,
                                    ])) ?: '-'),
                            ])
                            ->columns(3),

                        SC\Fieldset::make('Last Touch')
                            ->schema([
                                Forms\Components\Placeholder::make('display_last_source')
                                    ->label('Source')
                                    ->content(fn ($record) => $record?->last_source ?? '-'),

                                Forms\Components\Placeholder::make('display_last_medium')
                                    ->label('Medium')
                                    ->content(fn ($record) => $record?->last_medium ?? '-'),

                                Forms\Components\Placeholder::make('display_last_campaign')
                                    ->label('Campaign')
                                    ->content(fn ($record) => $record?->last_campaign ?? '-'),

                                Forms\Components\Placeholder::make('display_last_utm')
                                    ->label('UTM (Source / Medium / Campaign)')
                                    ->content(fn ($record) => implode(' / ', array_filter([
                                        $record?->last_utm_source,
                                        $record?->last_utm_medium,
                                        $record?->last_utm_campaign,
                                    ])) ?: '-'),
                            ])
                            ->columns(4),

                        SC\Fieldset::make('Click IDs')
                            ->schema([
                                Forms\Components\Placeholder::make('display_gclid')
                                    ->label('Google Ads (gclid)')
                                    ->content(fn ($record) => $record?->first_gclid
                                        ? new HtmlString('<span class="text-xs text-success-600 font-medium">First: ' . e(substr($record->first_gclid, 0, 20)) . '...</span>' . ($record->last_gclid ? '<br><span class="text-xs text-gray-500">Last: ' . e(substr($record->last_gclid, 0, 20)) . '...</span>' : ''))
                                        : new HtmlString('<span class="text-gray-400">-</span>')),

                                Forms\Components\Placeholder::make('display_fbclid')
                                    ->label('Facebook (fbclid)')
                                    ->content(fn ($record) => $record?->first_fbclid
                                        ? new HtmlString('<span class="text-xs text-info-600 font-medium">First: ' . e(substr($record->first_fbclid, 0, 20)) . '...</span>' . ($record->last_fbclid ? '<br><span class="text-xs text-gray-500">Last: ' . e(substr($record->last_fbclid, 0, 20)) . '...</span>' : ''))
                                        : new HtmlString('<span class="text-gray-400">-</span>')),

                                Forms\Components\Placeholder::make('display_ttclid')
                                    ->label('TikTok (ttclid)')
                                    ->content(fn ($record) => $record?->first_ttclid
                                        ? new HtmlString('<span class="text-xs text-warning-600 font-medium">' . e(substr($record->first_ttclid, 0, 20)) . '...</span>')
                                        : new HtmlString('<span class="text-gray-400">-</span>')),

                                Forms\Components\Placeholder::make('display_li_fat_id')
                                    ->label('LinkedIn (li_fat_id)')
                                    ->content(fn ($record) => $record?->first_li_fat_id
                                        ? new HtmlString('<span class="text-xs text-primary-600 font-medium">' . e(substr($record->first_li_fat_id, 0, 20)) . '...</span>')
                                        : new HtmlString('<span class="text-gray-400">-</span>')),
                            ])
                            ->columns(4),
                    ]),

                // ===== CROSS-TENANT & DEVICE =====
                SC\Section::make('Cross-Tenant & Device')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('display_primary_tenant')
                            ->label('Primary Tenant')
                            ->content(function ($record) {
                                if (!$record?->primary_tenant_id) return '-';
                                $tenant = Tenant::find($record->primary_tenant_id);
                                return $tenant ? ($tenant->public_name ?? $tenant->name) . " (ID: {$tenant->id})" : "ID: {$record->primary_tenant_id}";
                            }),

                        Forms\Components\Placeholder::make('display_tenant_count')
                            ->label('Tenant Count')
                            ->content(fn ($record) => $record?->tenant_count ?? 0),

                        Forms\Components\Placeholder::make('display_tenant_ids')
                            ->label('Tenant IDs')
                            ->content(fn ($record) => $record?->tenant_ids
                                ? implode(', ', (array) $record->tenant_ids)
                                : '-'),

                        Forms\Components\Placeholder::make('display_visitor_id')
                            ->label('Visitor ID')
                            ->content(fn ($record) => $record?->visitor_id
                                ? new HtmlString('<span class="text-xs font-mono">' . e($record->visitor_id) . '</span>')
                                : '-'),

                        Forms\Components\Placeholder::make('display_device_type')
                            ->label('Device Type')
                            ->content(fn ($record) => ucfirst($record?->device_type ?? $record?->primary_device ?? '-')),

                        Forms\Components\Placeholder::make('display_browser')
                            ->label('Browser')
                            ->content(fn ($record) => $record?->browser ?? $record?->primary_browser ?? '-'),

                        Forms\Components\Placeholder::make('display_os')
                            ->label('OS')
                            ->content(fn ($record) => $record?->os ?? '-'),

                        Forms\Components\Placeholder::make('display_ip')
                            ->label('IP Address')
                            ->content(fn ($record) => $record?->ip_address ?? '-'),
                    ])
                    ->columns(4),

                // ===== EMAIL ENGAGEMENT =====
                SC\Section::make('Email Engagement')
                    ->icon('heroicon-o-envelope')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('display_email_subscribed')
                            ->label('Subscribed')
                            ->content(fn ($record) => $record?->email_subscribed
                                ? new HtmlString('<span class="text-success-600 font-medium">Yes</span>')
                                : new HtmlString('<span class="text-danger-600 font-medium">No</span>' . ($record?->email_unsubscribed_at ? ' (since ' . $record->email_unsubscribed_at->format('d M Y') . ')' : ''))),

                        Forms\Components\Placeholder::make('display_emails_sent')
                            ->label('Emails Sent')
                            ->content(fn ($record) => $record?->emails_sent ?? 0),

                        Forms\Components\Placeholder::make('display_emails_opened')
                            ->label('Emails Opened')
                            ->content(fn ($record) => $record?->emails_opened ?? 0),

                        Forms\Components\Placeholder::make('display_emails_clicked')
                            ->label('Emails Clicked')
                            ->content(fn ($record) => $record?->emails_clicked ?? 0),

                        Forms\Components\Placeholder::make('display_email_open_rate')
                            ->label('Open Rate')
                            ->content(fn ($record) => $record?->email_open_rate !== null
                                ? number_format((float) $record->email_open_rate, 1) . '%'
                                : '-'),

                        Forms\Components\Placeholder::make('display_email_click_rate')
                            ->label('Click Rate')
                            ->content(fn ($record) => $record?->email_click_rate !== null
                                ? number_format((float) $record->email_click_rate, 1) . '%'
                                : '-'),

                        Forms\Components\Placeholder::make('display_last_email_opened')
                            ->label('Last Email Opened')
                            ->content(fn ($record) => $record?->last_email_opened_at?->format('d M Y H:i') ?? '-'),
                    ])
                    ->columns(4),

                // ===== CONSENT =====
                SC\Section::make('Consent & Privacy')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('display_marketing_consent')
                            ->label('Marketing Consent')
                            ->content(fn ($record) => self::renderConsentBadge($record?->marketing_consent)),

                        Forms\Components\Placeholder::make('display_analytics_consent')
                            ->label('Analytics Consent')
                            ->content(fn ($record) => self::renderConsentBadge($record?->analytics_consent)),

                        Forms\Components\Placeholder::make('display_personalization_consent')
                            ->label('Personalization Consent')
                            ->content(fn ($record) => self::renderConsentBadge($record?->personalization_consent)),

                        Forms\Components\Placeholder::make('display_consent_updated')
                            ->label('Consent Updated')
                            ->content(fn ($record) => $record?->consent_updated_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('display_consent_source')
                            ->label('Consent Source')
                            ->content(fn ($record) => $record?->consent_source ?? '-'),

                        Forms\Components\Placeholder::make('display_anonymized')
                            ->label('GDPR Anonymized')
                            ->content(fn ($record) => $record?->is_anonymized
                                ? new HtmlString('<span class="text-danger-600 font-medium">Yes</span> (' . ($record->anonymized_at?->format('d M Y') ?? '') . ')')
                                : 'No'),
                    ])
                    ->columns(3),

                // ===== EXTERNAL IDS =====
                SC\Section::make('External Integrations')
                    ->icon('heroicon-o-link')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('display_stripe_id')
                            ->label('Stripe Customer ID')
                            ->content(fn ($record) => $record?->stripe_customer_id
                                ? new HtmlString('<span class="font-mono text-xs">' . e($record->stripe_customer_id) . '</span>')
                                : '-'),

                        Forms\Components\Placeholder::make('display_facebook_id')
                            ->label('Facebook User ID')
                            ->content(fn ($record) => $record?->facebook_user_id ?? '-'),

                        Forms\Components\Placeholder::make('display_google_id')
                            ->label('Google User ID')
                            ->content(fn ($record) => $record?->google_user_id ?? '-'),

                        Forms\Components\Placeholder::make('display_cohort')
                            ->label('Cohort')
                            ->content(fn ($record) => $record?->cohort_month
                                ? 'Month: ' . $record->cohort_month . ($record->cohort_week ? ' | Week: ' . $record->cohort_week : '')
                                : '-'),
                    ])
                    ->columns(4),

                // ===== TAGS & NOTES (editable) =====
                SC\Section::make('Tags & Notes')
                    ->icon('heroicon-o-tag')
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
                    ->label('Customer')
                    ->getStateUsing(fn ($record) => $record->email ?? 'Anonymous')
                    ->description(fn ($record) => $record->full_name)
                    ->searchable(query: function ($query, $search) {
                        // Search by email hash or try to search by hashed email
                        $hash = hash('sha256', strtolower(trim($search)));
                        return $query->where(function ($q) use ($search, $hash) {
                            $q->where('email_hash', $hash)
                              ->orWhere('email_hash', 'like', "%{$search}%");
                        });
                    }),

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
                    ->getStateUsing(fn ($record) => number_format((float) ($record->total_spent ?? 0), 2) . ' ' . ($record->currency ?? 'EUR'))
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
                    ->label('High Value (500+)')
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

    // ===== HELPER METHODS =====

    protected static function getSegmentClass(string $segment): string
    {
        return match ($segment) {
            'VIP' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
            'Champions' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
            'Repeat Buyer' => 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400',
            'First-Time Buyer' => 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
            'At Risk', 'Lapsed VIP' => 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400',
            default => 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
        };
    }

    protected static function getRfmClass(string $segment): string
    {
        return match ($segment) {
            'Champions', 'Loyal' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
            'Potential Loyalist', 'New Customers', 'Promising' => 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400',
            'Need Attention', 'About To Sleep' => 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
            'At Risk', 'Cannot Lose Them' => 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400',
            'Hibernating', 'Lost' => 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
            default => 'bg-gray-50 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400',
        };
    }

    protected static function renderScoreBar(float $score, string $label, bool $invertColor = false): string
    {
        $color = $invertColor
            ? ($score >= 70 ? 'danger' : ($score >= 40 ? 'warning' : 'success'))
            : ($score >= 70 ? 'success' : ($score >= 40 ? 'warning' : 'danger'));

        $colorClass = match ($color) {
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger' => 'bg-danger-500',
            default => 'bg-gray-500',
        };

        return '<div class="flex items-center gap-2"><div class="w-24 bg-gray-200 rounded-full h-2 dark:bg-gray-700"><div class="' . $colorClass . ' h-2 rounded-full" style="width: ' . min(100, max(0, $score)) . '%"></div></div><span class="text-sm font-medium">' . number_format($score, 0) . '/100</span></div>';
    }

    protected static function renderConsentBadge(?bool $consent): HtmlString
    {
        if ($consent === null) {
            return new HtmlString('<span class="text-gray-400">Unknown</span>');
        }
        return $consent
            ? new HtmlString('<span class="text-success-600 font-medium">Granted</span>')
            : new HtmlString('<span class="text-danger-600 font-medium">Denied</span>');
    }

    // ===== EXPORT =====

    public static function exportCustomers($records = null): StreamedResponse
    {
        $filename = 'customers_export_' . now()->format('Y-m-d_His') . '.csv';

        return Response::streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

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
