<?php

namespace App\Filament\Resources\Customers;

use App\Models\Customer;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\PointsTransaction;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Colors\Color;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Customers\Pages\ViewCustomerStats;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'tenant-customers';

    // Filament v4 typing
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Tenant Customers';
    protected static UnitEnum|string|null $navigationGroup = 'Tenants';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(4)->schema([
                // ========== LEFT COLUMN (3/4) ==========
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Tabs::make('CustomerTabs')->tabs([

                        // TAB 1: Overview
                        SC\Tabs\Tab::make('Sinteză')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Forms\Components\Placeholder::make('overview')
                                    ->hiddenLabel()
                                    ->content(function (?Customer $record) {
                                        if (!$record || !$record->exists) return new HtmlString('<p style="color:#64748B;">Salvează clientul pentru a vedea sinteza.</p>');

                                        $ordersCount = $record->orders()->count();
                                        $totalSpent = $record->orders()->whereNotIn('status', ['cancelled', 'refunded', 'failed'])->sum('total');
                                        $ticketsCount = \App\Models\Ticket::whereHas('order', fn($q) => $q->where('customer_id', $record->id))->count();
                                        $points = static::getCustomerPoints($record);
                                        $pointsBalance = $points?->current_balance ?? 0;
                                        $tenantsCount = $record->tenants()->count();
                                        $lastOrder = $record->orders()->latest()->first();
                                        $lastOrderDate = $lastOrder?->created_at?->diffForHumans() ?? '—';
                                        $profilePct = $record->getProfileCompletionPercentage();
                                        $name = trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: '—';

                                        $stat = fn ($label, $value, $color = '#E2E8F0') =>
                                            "<div style='text-align:center;padding:16px;background:rgba(30,41,59,0.5);border-radius:12px;'>
                                                <div style='font-size:24px;font-weight:700;color:{$color};'>{$value}</div>
                                                <div style='font-size:11px;color:#64748B;margin-top:4px;'>{$label}</div>
                                            </div>";

                                        $row = fn ($label, $value) =>
                                            "<div style='display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(51,65,85,0.3);'>
                                                <span style='font-size:13px;color:#64748B;'>{$label}</span>
                                                <span style='font-size:13px;font-weight:600;color:#E2E8F0;'>{$value}</span>
                                            </div>";

                                        return new HtmlString("
                                            <div style='display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;'>
                                                {$stat('Comenzi', $ordersCount, '#60A5FA')}
                                                {$stat('Bilete', $ticketsCount, '#34D399')}
                                                {$stat('Total cheltuit', number_format($totalSpent, 2) . ' RON', '#F59E0B')}
                                                {$stat('Puncte', number_format($pointsBalance), '#A78BFA')}
                                            </div>
                                            <div style='display:grid;grid-template-columns:1fr 1fr;gap:20px;'>
                                                <div>
                                                    {$row('Nume', e($name))}
                                                    {$row('Email', e($record->email))}
                                                    {$row('Telefon', e($record->phone ?? '—'))}
                                                    {$row('Oraș', e($record->city ?? '—'))}
                                                    {$row('Țară', e($record->country ?? '—'))}
                                                </div>
                                                <div>
                                                    {$row('Profil complet', $profilePct . '%')}
                                                    {$row('Tenanți', $tenantsCount)}
                                                    {$row('Ultima comandă', $lastOrderDate)}
                                                    {$row('Înregistrat', $record->created_at?->format('d M Y') ?? '—')}
                                                    {$row('Referral code', e($record->referral_code ?? '—'))}
                                                </div>
                                            </div>
                                        ");
                                    }),
                            ]),

                        // TAB 2: Personal Details
                        SC\Tabs\Tab::make('Date personale')
                            ->icon('heroicon-o-user')
                            ->schema([
                                SC\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('first_name')->label('First name')->maxLength(120),
                                    Forms\Components\TextInput::make('last_name')->label('Last name')->maxLength(120),
                                ]),
                                SC\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('email')->email()->required()->maxLength(190),
                                    Forms\Components\TextInput::make('phone')->maxLength(60),
                                ]),
                                SC\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('city')->label('City')->maxLength(120),
                                    Forms\Components\TextInput::make('country')->label('Country')->maxLength(120),
                                ]),
                                Forms\Components\DatePicker::make('date_of_birth')->label('Date of birth'),
                            ]),

                        // TAB 3: Tenants
                        SC\Tabs\Tab::make('Tenanți')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Select::make('tenant_id')
                                    ->label('Tenant')
                                    ->relationship('tenant', 'name')
                                    ->searchable()->preload()->required(),
                                Forms\Components\Select::make('primary_tenant_id')
                                    ->label('Primary Tenant')
                                    ->relationship('primaryTenant', 'name')
                                    ->searchable()->preload(),
                                Forms\Components\Select::make('tenants')
                                    ->label('Member of Tenants')
                                    ->multiple()
                                    ->relationship('tenants', 'name')
                                    ->preload()
                                    ->helperText('Tenants where this customer has a relationship (e.g., orders).'),
                            ]),

                        // TAB 4: Orders
                        SC\Tabs\Tab::make('Comenzi')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Forms\Components\Placeholder::make('orders_list')
                                    ->hiddenLabel()
                                    ->content(function (?Customer $record) {
                                        if (!$record || !$record->exists) return new HtmlString('<p style="color:#64748B;">Salvează clientul pentru a vedea comenzile.</p>');

                                        $orders = $record->orders()->latest()->limit(20)->get();
                                        if ($orders->isEmpty()) return new HtmlString('<p style="color:#64748B;">Nu există comenzi.</p>');

                                        $html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-800/50"><tr>';
                                        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-400">Comandă</th>';
                                        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-400">Status</th>';
                                        $html .= '<th class="px-3 py-2 text-right font-medium text-gray-400">Total</th>';
                                        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-400">Sursa</th>';
                                        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-400">Data</th>';
                                        $html .= '</tr></thead><tbody>';

                                        foreach ($orders as $o) {
                                            $statusColor = match($o->status) {
                                                'completed', 'confirmed' => 'green', 'pending' => 'yellow',
                                                'cancelled' => 'red', 'refunded' => 'purple', default => 'gray',
                                            };
                                            $html .= '<tr class="border-t border-gray-800">';
                                            $html .= '<td class="px-3 py-2"><a href="' . route('filament.admin.resources.orders.edit', $o->id) . '" class="text-primary-400 hover:underline">' . e($o->order_number ?? '#' . $o->id) . '</a></td>';
                                            $html .= '<td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs bg-' . $statusColor . '-500/20 text-' . $statusColor . '-400">' . e($o->status) . '</span></td>';
                                            $html .= '<td class="px-3 py-2 text-right font-semibold">' . number_format($o->total ?? 0, 2) . ' ' . ($o->currency ?? 'RON') . '</td>';
                                            $html .= '<td class="px-3 py-2 text-gray-400">' . e($o->source ?? '—') . '</td>';
                                            $html .= '<td class="px-3 py-2 text-gray-400">' . ($o->created_at?->format('d.m.Y H:i') ?? '—') . '</td>';
                                            $html .= '</tr>';
                                        }
                                        $html .= '</tbody></table></div>';
                                        return new HtmlString($html);
                                    }),
                            ]),

                        // TAB 5: Loyalty Points
                        SC\Tabs\Tab::make('Puncte fidelitate')
                            ->icon('heroicon-o-star')
                            ->schema([
                                SC\Grid::make(3)->schema([
                                    Forms\Components\Placeholder::make('points_balance')
                                        ->label('Sold curent')
                                        ->content(fn ($record) => new HtmlString(
                                            '<div class="flex items-center gap-2">
                                                <span class="text-3xl font-bold text-amber-600">' . number_format(static::getCustomerPoints($record)?->current_balance ?? 0) . '</span>
                                                <span class="text-gray-500">puncte</span>
                                            </div>'
                                        )),
                                    Forms\Components\Placeholder::make('points_earned')
                                        ->label('Total câștigate')
                                        ->content(fn ($record) => new HtmlString(
                                            '<div class="flex items-center gap-2">
                                                <span class="text-2xl font-semibold text-green-600">+' . number_format(static::getCustomerPoints($record)?->total_earned ?? 0) . '</span>
                                                <span class="text-gray-500">puncte</span>
                                            </div>'
                                        )),
                                    Forms\Components\Placeholder::make('points_spent')
                                        ->label('Total cheltuite')
                                        ->content(fn ($record) => new HtmlString(
                                            '<div class="flex items-center gap-2">
                                                <span class="text-2xl font-semibold text-red-600">-' . number_format(static::getCustomerPoints($record)?->total_spent ?? 0) . '</span>
                                                <span class="text-gray-500">puncte</span>
                                            </div>'
                                        )),
                                ]),
                                SC\Fieldset::make('Ajustare manuală puncte')->schema([
                                    Forms\Components\Select::make('points_action')->label('Acțiune')
                                        ->options(['add' => 'Adaugă puncte', 'subtract' => 'Scade puncte'])
                                        ->native(false)->dehydrated(false),
                                    Forms\Components\TextInput::make('points_amount')->label('Cantitate')
                                        ->numeric()->minValue(1)->dehydrated(false),
                                    Forms\Components\TextInput::make('points_reason')->label('Motiv')
                                        ->placeholder('Ex: Bonus aniversar, Corecție, etc.')->dehydrated(false),
                                ])->columns(3),
                                Forms\Components\Placeholder::make('points_history')
                                    ->label('Istoric puncte (ultimele 10 tranzacții)')
                                    ->content(fn ($record) => new HtmlString(static::renderPointsHistory($record)))
                                    ->columnSpanFull(),
                            ]),

                        // TAB 6: Meta
                        SC\Tabs\Tab::make('Meta')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Forms\Components\KeyValue::make('meta')
                                    ->keyLabel('Key')->valueLabel('Value')
                                    ->columnSpanFull()
                                    ->addable()->deletable()->reorderable(),
                            ]),
                    ]),
                ]),

                // ========== RIGHT SIDEBAR (1/4) ==========
                SC\Group::make()->columnSpan(1)->schema([

                    // Customer Preview Card
                    SC\Section::make('')->compact()->schema([
                        Forms\Components\Placeholder::make('customer_preview')
                            ->hiddenLabel()
                            ->content(function (?Customer $record) {
                                if (!$record || !$record->exists) return '';
                                $name = trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Client';
                                $initials = collect(explode(' ', $name))->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->join('');
                                $email = $record->email ?? '';
                                $phone = $record->phone ?? '';
                                $profilePct = $record->getProfileCompletionPercentage();
                                $pctColor = $profilePct >= 80 ? '#10B981' : ($profilePct >= 50 ? '#F59E0B' : '#EF4444');

                                return new HtmlString("
                                    <div style='display:flex;gap:12px;align-items:center;'>
                                        <div style='width:48px;height:48px;border-radius:50%;background:#334155;display:flex;align-items:center;justify-content:center;font-weight:700;color:#E2E8F0;font-size:16px;'>{$initials}</div>
                                        <div>
                                            <div style='font-size:16px;font-weight:700;color:white;'>" . e($name) . "</div>
                                            <div style='font-size:12px;color:#64748B;'>" . e($email) . "</div>
                                        </div>
                                    </div>
                                    <div style='margin-top:10px;display:flex;flex-wrap:wrap;gap:4px;'>
                                        <span style='display:inline-block;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;background:{$pctColor}20;color:{$pctColor};'>Profil {$profilePct}%</span>
                                    </div>
                                ");
                            }),
                    ]),

                    // Statistics
                    SC\Section::make('Statistici')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visible(fn (?Customer $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('sidebar_stats')
                                ->hiddenLabel()
                                ->content(function (?Customer $record) {
                                    if (!$record) return '';
                                    $ordersCount = $record->orders()->count();
                                    $totalSpent = $record->orders()->whereNotIn('status', ['cancelled', 'refunded', 'failed'])->sum('total');
                                    $ticketsCount = \App\Models\Ticket::whereHas('order', fn($q) => $q->where('customer_id', $record->id))->count();
                                    $points = static::getCustomerPoints($record);

                                    $row = fn ($label, $value, $color = '#E2E8F0') =>
                                        "<div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                                            <span style='font-size:12px;color:#64748B;'>{$label}</span>
                                            <span style='font-size:12px;font-weight:600;color:{$color};'>{$value}</span>
                                        </div>";

                                    return new HtmlString(
                                        $row('Comenzi', $ordersCount, '#60A5FA') .
                                        $row('Bilete', $ticketsCount, '#34D399') .
                                        $row('Total cheltuit', number_format($totalSpent, 2) . ' RON', '#F59E0B') .
                                        $row('Puncte', number_format($points?->current_balance ?? 0), '#A78BFA')
                                    );
                                }),
                        ]),

                    // Info
                    SC\Section::make('Info')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn (?Customer $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(function (?Customer $record) {
                                    if (!$record) return '';
                                    $row = fn ($label, $value) =>
                                        "<div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                                            <span style='font-size:12px;color:#64748B;'>{$label}</span>
                                            <span style='font-size:12px;font-weight:600;color:#E2E8F0;'>{$value}</span>
                                        </div>";
                                    return new HtmlString(
                                        $row('Înregistrat', $record->created_at?->format('d M Y') ?? '—') .
                                        $row('Modificat', $record->updated_at?->diffForHumans() ?? '—') .
                                        $row('ID', "<span style='font-family:monospace;color:#64748B;'>{$record->id}</span>")
                                    );
                                }),
                        ]),

                    // Quick Actions
                    SC\Section::make('Acțiuni')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visible(fn (?Customer $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('quick_actions')
                                ->hiddenLabel()
                                ->content(function (?Customer $record) {
                                    if (!$record) return '';
                                    $ordersUrl = route('filament.admin.resources.orders.index') . '?tableSearch=' . urlencode($record->email);
                                    $statsUrl = static::getUrl('stats', ['record' => $record]);
                                    $mailUrl = 'mailto:' . e($record->email);
                                    $btn = "display:flex;align-items:center;justify-content:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:600;border-radius:8px;text-decoration:none;transition:opacity 0.15s;";
                                    return new HtmlString("
                                        <div style='display:flex;flex-direction:column;gap:6px;'>
                                            <a href='{$ordersUrl}' target='_blank' style='{$btn}background:rgba(96,165,250,0.15);color:#60A5FA;'>Comenzi</a>
                                            <a href='{$statsUrl}' style='{$btn}background:rgba(52,211,153,0.15);color:#34D399;'>Statistici</a>
                                            <a href='{$mailUrl}' style='{$btn}background:rgba(148,163,184,0.15);color:#94A3B8;'>Trimite email</a>
                                        </div>
                                    ");
                                }),
                        ]),
                ]),
            ]),
        ]);
    }

    /**
     * Get CustomerPoints record for a customer from the Gamification system
     */
    protected static function getCustomerPoints($record): ?CustomerPoints
    {
        if (!$record) {
            return null;
        }

        $tenantId = $record->primary_tenant_id ?? $record->tenant_id;

        if (!$tenantId) {
            return null;
        }

        return CustomerPoints::where('tenant_id', $tenantId)
            ->where('customer_id', $record->id)
            ->first();
    }

    protected static function renderPointsHistory($record): string
    {
        if (!$record) {
            return '<p class="text-gray-500 text-sm">Salvați clientul pentru a vedea istoricul punctelor.</p>';
        }

        $tenantId = $record->primary_tenant_id ?? $record->tenant_id;

        if (!$tenantId) {
            return '<p class="text-gray-500 text-sm">Clientul nu are un tenant asociat.</p>';
        }

        // Get points transactions from Gamification system
        $transactions = PointsTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $record->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($transactions->isEmpty()) {
            return '<p class="text-gray-500 text-sm">Nu există tranzacții de puncte.</p>';
        }

        $html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
        $html .= '<thead class="bg-gray-50"><tr>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Data</th>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Tip</th>';
        $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600">Puncte</th>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Descriere</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($transactions as $tx) {
            $pointsClass = $tx->points >= 0 ? 'text-green-600' : 'text-red-600';
            $pointsPrefix = $tx->points >= 0 ? '+' : '';
            $typeLabel = match($tx->type) {
                'earned' => '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">Câștigate</span>',
                'spent' => '<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">Cheltuite</span>',
                'expired' => '<span class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs">Expirate</span>',
                'adjusted' => '<span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">Ajustare</span>',
                'refunded' => '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Returnat</span>',
                default => '<span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">' . ucfirst($tx->type) . '</span>',
            };

            // Handle JSON description
            $description = $tx->description;
            if (is_array($description)) {
                $description = $description['ro'] ?? $description['en'] ?? reset($description) ?? '-';
            }
            $description = $description ?: ($tx->admin_note ?? '-');

            $html .= '<tr class="border-t border-gray-100">';
            $html .= '<td class="px-3 py-2 text-gray-600">' . $tx->created_at->format('d.m.Y H:i') . '</td>';
            $html .= '<td class="px-3 py-2">' . $typeLabel . '</td>';
            $html .= '<td class="px-3 py-2 text-right font-semibold ' . $pointsClass . '">' . $pointsPrefix . number_format($tx->points) . '</td>';
            $html .= '<td class="px-3 py-2 text-gray-600">' . e($description) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->url(fn (\App\Models\Customer $record) => static::getUrl('profile', ['record' => $record])),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant_display')
                    ->label('Tenant / Source')
                    ->state(function (\App\Models\Customer $record) {
                        $marketplace = $record->marketplaceClient?->name;
                        $tenant = $record->tenant?->name;
                        if ($marketplace && $tenant) {
                            return "{$marketplace} → {$tenant}";
                        }
                        return $marketplace ?? $tenant ?? '-';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')   // necesită ->orders() pe modelul Customer
                    ->sortable(),

                Tables\Columns\TextColumn::make('points_balance')
                    ->label('Puncte')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('profile_completion')
                    ->label('Profil %')
                    ->state(fn (\App\Models\Customer $record) => $record->getProfileCompletionPercentage() . '%')
                    ->badge()
                    ->color(fn (\App\Models\Customer $record) => match(true) {
                        $record->getProfileCompletionPercentage() >= 80 => 'success',
                        $record->getProfileCompletionPercentage() >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('primary_tenant_display')
                    ->label('Primary Tenant')
                    ->state(function (\App\Models\Customer $record) {
                        $marketplace = $record->marketplaceClient?->name;
                        $tenant = $record->primaryTenant?->name;
                        if ($marketplace && $tenant) {
                            return "{$marketplace} → {$tenant}";
                        }
                        return $marketplace ?? $tenant ?? '-';
                    }
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('view_orders')
                    ->label('View Orders')
                    ->state('Open')
                    ->url(fn ($record) => route('filament.admin.resources.orders.index') . '?tableSearch=' . urlencode($record->email))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('profile_link')
                    ->label('Profil')
                    ->state('Profil')
                    ->url(fn ($record) => static::getUrl('profile', ['record' => $record]))
                    ->openUrlInNewTab(false),

                Tables\Columns\TextColumn::make('stats_link')
                    ->label('Stats')
                    ->state('Open')
                    ->url(fn ($record) => static::getUrl('stats', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
            'profile' => Pages\ViewCustomer::route('/{record}/profile'),
            'stats'  => Pages\ViewCustomerStats::route('/{record}/stats'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('orders')
            ->with(['tenant', 'primaryTenant', 'marketplaceClient']);
    }
}
