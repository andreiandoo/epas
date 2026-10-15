<?php

namespace App\Filament\Resources\Tenants;

use App\Models\Domain;
use App\Models\Tenant;
use App\Services\CloudflareService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Acțiunile din tab-ul „Domains & Deployment” pentru site-urile găzduite de noi:
 * subdomeniu pe unul din domeniile noastre și deschiderea editorului de site.
 */
class TenantSiteActions
{
    public static function generateSubdomain(): Action
    {
        return Action::make('generate_subdomain')
            ->label('Generează subdomeniu')
            ->icon('heroicon-o-plus-circle')
            ->visible(fn (?Tenant $record) => $record !== null)
            ->modalHeading('Subdomeniu pe un domeniu Tixello')
            ->modalDescription('Pentru tenanții fără domeniu propriu. Subdomeniul merge imediat (wildcard DNS); site-ul apare după ce îl publici din „Site”.')
            ->schema([
                Forms\Components\TextInput::make('subdomain')
                    ->label('Subdomeniu')
                    ->required()
                    ->minLength(3)->maxLength(63)
                    ->regex('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
                    ->validationMessages(['regex' => 'Doar litere mici, cifre și cratimă (nu la început sau sfârșit).'])
                    ->default(fn (?Tenant $record) => $record ? self::suggest($record) : null)
                    ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state))),
                Forms\Components\Select::make('base_domain')
                    ->label('Domeniu')
                    ->options(self::activeBaseDomains())
                    ->default(array_key_first(self::activeBaseDomains()))
                    ->required()
                    ->helperText('Apar doar domeniile pregătite pe server (config/tenant-sites.php)'),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Domeniu principal')
                    ->default(fn (?Tenant $record) => $record && ! $record->domains()->where('is_primary', true)->exists()),
            ])
            ->action(function (array $data, Tenant $record) {
                $sub = $data['subdomain'];
                $base = $data['base_domain'];
                $full = "{$sub}.{$base}";

                $reserved = array_merge(app(CloudflareService::class)->getReservedSubdomains(), config('tenant-sites.reserved', []));
                if (in_array($sub, $reserved, true)) {
                    Notification::make()->title("„{$sub}” este rezervat")->danger()->send();

                    return;
                }
                if (! array_key_exists($base, self::activeBaseDomains())) {
                    Notification::make()->title("{$base} nu este pregătit pe server")->danger()->send();

                    return;
                }
                if (Domain::where('domain', $full)->exists()) {
                    Notification::make()->title("{$full} este deja folosit")->danger()->send();

                    return;
                }

                DB::transaction(function () use ($record, $data, $sub, $base, $full) {
                    if ($data['is_primary']) {
                        $record->domains()->update(['is_primary' => false]);
                    }
                    Domain::create([
                        'tenant_id' => $record->id,
                        'domain' => $full,
                        'is_primary' => (bool) $data['is_primary'],
                        'is_active' => true,
                        'is_managed_subdomain' => true,
                        'subdomain' => $sub,
                        'base_domain' => $base,
                        'activated_at' => now(),
                        'notes' => 'Subdomeniu generat din core (site găzduit de Tixello)',
                    ]);
                    if ($data['is_primary'] || empty($record->domain)) {
                        $record->update(['domain' => $full]);
                    }
                });

                $record->load('domains');
                Notification::make()
                    ->title("{$full} a fost creat")
                    ->body('Configurează și publică site-ul din „Site”.')
                    ->success()
                    ->send();
            });
    }

    public static function openSiteEditor(): Action
    {
        return Action::make('open_site_editor')
            ->label('Site (template)')
            ->icon('heroicon-o-paint-brush')
            ->color('gray')
            ->visible(fn (?Tenant $record) => $record !== null)
            ->url(fn (?Tenant $record) => $record ? TenantResource::getUrl('site', ['record' => $record]) : null);
    }

    /** @return array<string, string> */
    public static function activeBaseDomains(): array
    {
        $out = [];
        foreach (config('tenant-sites.base_domains', []) as $domain => $cfg) {
            if (! empty($cfg['active'])) {
                $out[$domain] = $domain;
            }
        }

        return $out;
    }

    private static function suggest(Tenant $tenant): string
    {
        $slug = \Illuminate\Support\Str::slug($tenant->public_name ?: $tenant->name);

        return substr($slug, 0, 40) ?: 'site-' . $tenant->id;
    }
}
