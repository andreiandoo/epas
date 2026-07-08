<?php

namespace App\Filament\Pages;

use App\Models\ApiKey;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ApiKeys extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 70;
    protected static ?string $title = 'API Keys';
    protected string $view = 'filament.pages.api-keys';

    public ?string $newKeyValue = null;

    /**
     * Canonical scope catalog. Keeping this in one place gives operators
     * a checkbox picker in the create form instead of a raw JSON textarea,
     * and lets us evolve the list without letting typos land in the DB.
     */
    public static function availableScopes(): array
    {
        return [
            'read.catalog' => 'Read catalog (venues, artists, events — public listings)',
            'read.analytics.artist' => 'Read artist analytics (KPIs, audience, sales intelligence)',
            'read.analytics.venue' => 'Read venue analytics (health score, event performance, personas)',
            'read.sales.aggregate' => 'Read sales aggregates (revenue/tickets by period, no PII)',
            'read.sales.raw' => 'Read raw orders / tickets (includes hashed buyer IDs)',
            'export.customers' => 'Export customer datasets (superfans, mailing lists)',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Generate New Key')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Key Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('A descriptive name for this API key'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->helperText('What will this key be used for?'),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->native(false)
                        ->helperText('Leave empty for no expiration'),

                    Forms\Components\CheckboxList::make('scopes')
                        ->label('Scopes')
                        ->options(static::availableScopes())
                        ->columns(1)
                        ->helperText('Leave empty for legacy behavior (no scope enforcement — the key can hit any api.key-protected endpoint). Select one or more to restrict.'),

                    Forms\Components\TextInput::make('rate_limit')
                        ->label('Rate limit (requests / minute)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(6000)
                        ->placeholder('Empty = route default')
                        ->helperText('Only enforced on routes that use the "throttle:apikey" limiter. Empty = fall back to the route\'s own throttle group.'),

                    Forms\Components\TagsInput::make('allowed_ips')
                        ->label('Allowed IPs')
                        ->placeholder('Add IP, press Enter')
                        ->helperText('Leave empty to accept from any IP (legacy behavior). Each entry is compared with exact match against the caller IP.'),

                    Forms\Components\Toggle::make('require_signature')
                        ->label('Require HMAC signature')
                        ->helperText('When on, requests must include X-Timestamp + X-Signature headers signed with the secret key.'),
                ])
                ->action(function (array $data) {
                    $key = Str::random(64);

                    ApiKey::create([
                        'name' => $data['name'],
                        'key' => $key,
                        'description' => $data['description'] ?? null,
                        'expires_at' => $data['expires_at'] ?? null,
                        // Only persist the new fields when the operator
                        // actually filled them — an empty checkbox list
                        // must remain NULL so the middleware treats the
                        // key as legacy (no scope restriction), NOT as
                        // "restricted to zero scopes".
                        'scopes' => !empty($data['scopes']) ? array_values($data['scopes']) : null,
                        'rate_limit' => !empty($data['rate_limit']) ? (int) $data['rate_limit'] : null,
                        'allowed_ips' => !empty($data['allowed_ips']) ? array_values($data['allowed_ips']) : null,
                        'require_signature' => (bool) ($data['require_signature'] ?? false),
                    ]);

                    $this->newKeyValue = $key;

                    Notification::make()
                        ->title('API Key Created')
                        ->body('Copy the key now - it won\'t be shown again!')
                        ->warning()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(ApiKey::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label('Key (partial)')
                    ->getStateUsing(fn ($record) => substr($record->key, 0, 8) . '...' . substr($record->key, -4))
                    ->copyable()
                    ->copyableState(fn ($record) => $record->key),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn ($record) => is_array($record->scopes) ? implode(',', $record->scopes) : 'legacy · unrestricted')
                    ->color(fn ($state) => $state === 'legacy · unrestricted' ? 'gray' : 'info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rate_limit')
                    ->label('Rate / min')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('allowed_ips_count')
                    ->label('IP Allowlist')
                    ->getStateUsing(fn ($record) => is_array($record->allowed_ips)
                        ? (count($record->allowed_ips) . ' IP' . (count($record->allowed_ips) === 1 ? '' : 's'))
                        : 'any')
                    ->color(fn ($state) => $state === 'any' ? 'gray' : 'success')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('require_signature')
                    ->label('HMAC')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_requests')
                    ->label('Requests')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->isSuperAdmin();
    }
}
