<?php

namespace App\Services\Onboarding;

use App\Enums\TenantType;
use App\Mail\Signup\SignupCodeMail;
use App\Mail\Signup\TenantAwaitingApprovalMail;
use App\Models\Domain;
use App\Models\Microservice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CloudflareService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Înscrierea unui tenant nou la /inregistrare. Contul se creează la primul pas
 * (fără parolă ținută în sesiune), restul pașilor completează tenantul, iar la
 * final înscrierea intră în coada de aprobare din core.
 */
class SignupService
{
    public const PLANS = [
        'exclusive' => ['plan' => '1percent', 'commission_rate' => 1.00],
        'mixed' => ['plan' => '2percent', 'commission_rate' => 2.00],
        'reseller' => ['plan' => '3percent', 'commission_rate' => 3.00],
    ];

    public function __construct(private CloudflareService $cloudflare) {}

    /** Pasul 1: user + tenant schiță, cu acordul pentru termeni salvat. */
    public function createAccount(array $data, ?string $ip): User
    {
        return DB::transaction(function () use ($data, $ip) {
            $fullName = trim($data['first_name'] . ' ' . $data['last_name']);

            $user = User::create([
                'name' => $fullName,
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'tenant',
            ]);

            Tenant::create([
                'name' => $fullName,
                'public_name' => $fullName,
                'owner_id' => $user->id,
                'slug' => 'inscriere-' . Str::lower(Str::random(12)),
                'status' => 'pending',
                'approval_status' => 'incomplete',
                'onboarding_step' => 2,
                'locale' => 'ro',
                'country' => 'RO',
                'commission_mode' => 'included',
                'plan' => self::PLANS['mixed']['plan'],
                'commission_rate' => self::PLANS['mixed']['commission_rate'],
                'payment_processor_mode' => 'test',
                'contact_first_name' => $data['first_name'],
                'contact_last_name' => $data['last_name'],
                'contact_email' => strtolower($data['email']),
                'contact_phone' => $data['phone'],
                'terms_accepted_at' => now(),
                'terms_version' => config('onboarding.terms_version'),
                'terms_ip' => $ip,
                // Tenanții noi pornesc cu meniul simplu și pagina „Primii pași”
                'settings' => ['panel' => ['simple_menu' => true]],
            ]);

            return $user;
        });
    }

    /** Trimite un cod nou de 6 cifre. Întoarce false dacă s-a cerut prea repede. */
    public function sendCode(User $user): bool
    {
        $key = $this->codeKey($user);
        $current = Cache::get($key);
        if ($current && now()->timestamp - ($current['sent_at'] ?? 0) < config('onboarding.code_resend_seconds')) {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($key, [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now()->timestamp,
        ], now()->addMinutes(config('onboarding.code_ttl_minutes')));

        app(SignupMailer::class)->send($user->email, new SignupCodeMail($user, $code), 'signup_code', $user->ownedTenant?->id);

        return true;
    }

    /** @return string ok | invalid | expired | too_many */
    public function verifyCode(User $user, string $code): string
    {
        $key = $this->codeKey($user);
        $stored = Cache::get($key);
        if (! $stored) {
            return 'expired';
        }
        if ($stored['attempts'] >= config('onboarding.code_max_attempts')) {
            return 'too_many';
        }
        if (! Hash::check(trim($code), $stored['hash'])) {
            $stored['attempts']++;
            Cache::put($key, $stored, now()->addMinutes(config('onboarding.code_ttl_minutes')));

            return 'invalid';
        }

        Cache::forget($key);
        $user->forceFill(['email_verified_at' => now()])->save();

        return 'ok';
    }

    /** Pasul 2: ce organizează, numele public și metoda de lucru. */
    public function saveOrganizer(Tenant $tenant, array $data): void
    {
        $plan = self::PLANS[$data['work_method']] ?? self::PLANS['mixed'];

        $tenant->update([
            'tenant_type' => TenantType::from($data['tenant_type']),
            'public_name' => $data['public_name'],
            'slug' => $this->uniqueSlug($data['public_name'], $tenant->id),
            'work_method' => $data['work_method'],
            'plan' => $plan['plan'],
            'commission_rate' => $plan['commission_rate'],
            'estimated_monthly_tickets' => (int) ($data['estimated_monthly_tickets'] ?? 0),
            'onboarding_step' => max(3, (int) $tenant->onboarding_step),
        ]);

        $this->applyCommission($tenant);
    }

    /** Pasul 7 (opțional): funcții în plus și site-ul făcut special. */
    public function saveExtras(Tenant $tenant, array $microserviceIds, bool $customSite): void
    {
        $ids = Microservice::active()->whereIn('id', $microserviceIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->saveChoice($tenant, 'extras', ['microservices' => $ids, 'custom_site' => $customSite], 8);
        $this->applyCommission($tenant->fresh());
    }

    /**
     * Comisionul: 1% sau 2% după modul de vânzare, plus un punct dacă a ales o funcție
     * care ridică comisionul sau site-ul făcut special. Creșterea se aplică o singură dată.
     */
    public function applyCommission(Tenant $tenant): void
    {
        $base = (float) (self::PLANS[$tenant->work_method]['commission_rate'] ?? self::PLANS['mixed']['commission_rate']);
        $rate = $base + ($this->raisesCommission($tenant) ? 1 : 0);

        $tenant->update(['commission_rate' => $rate, 'plan' => ((int) $rate) . 'percent']);
    }

    public function raisesCommission(Tenant $tenant): bool
    {
        $extras = $tenant->signup_choices['extras'] ?? [];
        if (! empty($extras['custom_site'])) {
            return true;
        }
        $ids = $extras['microservices'] ?? [];

        return $ids && Microservice::whereIn('id', $ids)->where('raises_commission', true)->exists();
    }

    /** Pasul 3: cine facturează. Pentru SRL/PFA datele vin din ANAF, verificate în controller. */
    public function saveBilling(Tenant $tenant, array $data): void
    {
        $type = $data['legal_entity_type'];
        $isPerson = $type === 'pf';

        $tenant->update([
            'legal_entity_type' => $type,
            'name' => $isPerson ? trim($tenant->contact_first_name . ' ' . $tenant->contact_last_name) : $data['company_name'],
            'company_name' => $isPerson ? trim($tenant->contact_first_name . ' ' . $tenant->contact_last_name) : $data['company_name'],
            'cui' => $isPerson ? null : $data['cui'],
            'reg_com' => $isPerson ? null : ($data['reg_com'] ?? null),
            'vat_payer' => $isPerson ? false : (bool) ($data['vat_payer'] ?? false),
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'] ?? null,
            'identity_data' => $isPerson ? [
                'cnp' => $data['cnp'],
                'id_series' => strtoupper($data['id_series']),
                'id_number' => $data['id_number'],
            ] : null,
            'settings' => array_merge($tenant->settings ?? [], ['business_type' => $isPerson ? 'persoana_fizica' : $type]),
            'onboarding_step' => max(4, (int) $tenant->onboarding_step),
        ]);
    }

    /** Pașii 4–6 (opționali): se rețin în signup_choices și se aplică la aprobare. */
    public function saveChoice(Tenant $tenant, string $key, ?array $value, int $nextStep): void
    {
        $choices = $tenant->signup_choices ?? [];
        $choices[$key] = $value;

        $tenant->update([
            'signup_choices' => $choices,
            'onboarding_step' => max($nextStep, (int) $tenant->onboarding_step),
        ]);
    }

    /** Subdomeniul e liber: nu e rezervat, nu e folosit și nu l-a ales altă înscriere în așteptare. */
    public function subdomainAvailable(string $subdomain, ?int $exceptTenantId = null): bool
    {
        if (! $this->cloudflare->isValidSubdomain($subdomain) || in_array($subdomain, $this->cloudflare->getReservedSubdomains(), true)) {
            return false;
        }

        $base = $this->cloudflare->getBaseDomain();
        $taken = Domain::where('domain', "{$subdomain}.{$base}")
            ->orWhere(fn ($q) => $q->where('subdomain', $subdomain)->where('base_domain', $base))
            ->exists();
        if ($taken) {
            return false;
        }

        return ! Tenant::whereIn('approval_status', ['incomplete', 'pending'])
            ->when($exceptTenantId, fn ($q) => $q->where('id', '!=', $exceptTenantId))
            ->where('signup_choices->site->subdomain', $subdomain)
            ->exists();
    }

    /** Propunerea de subdomeniu din numele public, prima variantă liberă. */
    public function suggestSubdomain(string $publicName, ?int $tenantId = null): ?string
    {
        $base = Str::of(Str::slug($publicName))->limit(40, '')->trim('-')->value();
        if (strlen($base) < 3) {
            $base = 'organizator';
        }
        for ($i = 0; $i < 20; $i++) {
            $candidate = $i === 0 ? $base : $base . '-' . ($i + 1);
            if ($this->subdomainAvailable($candidate, $tenantId)) {
                return $candidate;
            }
        }

        return null;
    }

    public function domainAvailable(string $domain): bool
    {
        return ! Domain::where('domain', $domain)->exists();
    }

    /** Trimite înscrierea spre aprobare. Întoarce lista pașilor lipsă (goală = trimisă). */
    public function submit(Tenant $tenant, User $user): array
    {
        $missing = [];
        if (! $user->email_verified_at) {
            $missing[] = 'email';
        }
        if (! $tenant->tenant_type) {
            $missing[] = 'organizer';
        }
        if (! $tenant->legal_entity_type) {
            $missing[] = 'billing';
        }
        if ($missing) {
            return $missing;
        }

        $site = $tenant->signup_choices['site'] ?? null;
        $domain = null;
        if (($site['mode'] ?? null) === 'subdomain' && ! empty($site['subdomain'])) {
            $domain = $site['subdomain'] . '.' . $this->cloudflare->getBaseDomain();
        } elseif (($site['mode'] ?? null) === 'own' && ! empty($site['domain'])) {
            $domain = $site['domain'];
        }

        $tenant->update([
            'approval_status' => 'pending',
            'approval_submitted_at' => now(),
            'domain' => $tenant->domain ?: $domain,
            'has_own_website' => ($site['mode'] ?? null) === 'own',
            'payment_processor' => $tenant->signup_choices['payments']['processor'] ?? null,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'onboarding_step' => 9,
        ]);

        $this->notifyAdmins($tenant);

        return [];
    }

    private function notifyAdmins(Tenant $tenant): void
    {
        $recipients = config('onboarding.admin_emails') ?: array_filter([Setting::current()?->email]);
        if (! $recipients) {
            Log::warning('Signup: nu am cui trimite anunțul de tenant nou', ['tenant_id' => $tenant->id]);

            return;
        }

        try {
            app(SignupMailer::class)->send($recipients, new TenantAwaitingApprovalMail($tenant), 'signup_admin_notice', $tenant->id);
        } catch (\Throwable $e) {
            Log::error('Signup: anunțul de tenant nou nu a plecat', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    private function uniqueSlug(string $name, int $tenantId): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->where('id', '!=', $tenantId)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function codeKey(User $user): string
    {
        return 'signup:email-code:' . $user->id;
    }
}
