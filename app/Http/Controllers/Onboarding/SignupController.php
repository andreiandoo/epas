<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSiteDemo;
use App\Services\AnafService;
use App\Services\CloudflareService;
use App\Services\LocationService;
use App\Services\Onboarding\SignupService;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\TenantSites\TemplateRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Înscrierea nouă a tenanților (/inregistrare). Contul se creează la primul pas,
 * restul pașilor se salvează pe tenant, iar la final înscrierea intră în coada
 * „Tenanți de aprobat”. /register (OnboardingController) rămâne neatins până la trecere.
 */
class SignupController extends Controller
{
    public function __construct(
        private SignupService $signup,
        private AnafService $anaf,
        private CloudflareService $cloudflare,
    ) {}

    public function show(LocationService $locations, TemplateRegistry $templates)
    {
        $tenant = $this->currentTenant();
        if (Auth::check() && ! $tenant) {
            // Cont existent care nu e o înscriere nouă: are deja panoul lui
            return redirect('/tenant');
        }
        if ($tenant && ! $tenant->isSignupIncomplete() && ! $tenant->isAwaitingApproval() && $tenant->approval_status !== 'rejected') {
            return redirect('/tenant');
        }

        return view('onboarding.signup', [
            'state' => $this->state($tenant),
            'tenantTypes' => collect(TenantType::cases())
                ->reject(fn (TenantType $t) => $t === TenantType::TenantArtist)
                ->map(fn (TenantType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'counties' => $locations->getStates('ro'),
            'templates' => $this->templateOptions($templates),
            'processors' => collect(PaymentProcessorFactory::getAvailableProcessors())
                ->map(fn ($p, $key) => ['value' => $key, 'label' => $p['name'] ?? $key])
                ->values(),
            'baseDomain' => $this->cloudflare->getBaseDomain(),
        ]);
    }

    /** Pasul 1: contul. */
    public function account(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return response()->json(['message' => 'Ești deja autentificat.'], 409);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ().-]{8,20}$/'],
            'password' => 'required|string|min:8|max:190|confirmed',
            'accept_terms' => 'accepted',
        ], [
            'email.unique' => 'Există deja un cont cu acest email. Intră în cont din /tenant/login.',
            'accept_terms.accepted' => 'Trebuie să accepți termenii și politica de confidențialitate.',
            'phone.regex' => 'Numărul de telefon nu pare corect.',
        ]);

        $user = $this->signup->createAccount($data, $request->ip());
        Auth::login($user);
        $request->session()->regenerate();

        try {
            $this->signup->sendCode($user);
        } catch (\Throwable $e) {
            Log::error('Signup: codul de verificare nu a plecat', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['state' => $this->state($this->currentTenant())]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant();
        $request->validate(['code' => 'required|digits:6']);

        $result = $this->signup->verifyCode($request->user(), $request->input('code'));
        if ($result !== 'ok') {
            $message = [
                'invalid' => 'Codul nu e corect.',
                'expired' => 'Codul a expirat. Cere unul nou.',
                'too_many' => 'Prea multe încercări. Cere un cod nou.',
            ][$result];

            throw ValidationException::withMessages(['code' => $message]);
        }

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    public function resendCode(Request $request): JsonResponse
    {
        $this->signupTenant();
        if ($request->user()->email_verified_at) {
            return response()->json(['message' => 'Emailul e deja confirmat.']);
        }

        $sent = $this->signup->sendCode($request->user());

        return $sent
            ? response()->json(['message' => 'Am trimis un cod nou.'])
            : response()->json(['message' => 'Așteaptă un minut înainte să ceri alt cod.'], 429);
    }

    /** Pasul 2: ce organizează. */
    public function organizer(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $data = $request->validate([
            'tenant_type' => ['required', Rule::in(array_map(fn ($t) => $t->value, TenantType::cases()))],
            'public_name' => 'required|string|min:2|max:120',
            'work_method' => 'required|in:exclusive,mixed,reseller',
            'estimated_monthly_tickets' => 'nullable|integer|min:0|max:10000000',
        ]);

        $this->signup->saveOrganizer($tenant, $data);

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    public function lookupCui(Request $request): JsonResponse
    {
        $this->signupTenant(requireVerified: true);
        $request->validate(['cui' => 'required|string|max:20']);

        $company = $this->findCompany($request->input('cui'));

        return response()->json(['company' => $company]);
    }

    /** Pasul 3: cine facturează. */
    public function billing(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $type = $request->input('legal_entity_type');

        $rules = [
            'legal_entity_type' => 'required|in:pf,pfa,srl',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:12',
        ];
        if ($type === 'pf') {
            $rules += [
                'cnp' => ['required', 'digits:13', function ($attr, $value, $fail) {
                    if (! self::validCnp((string) $value)) {
                        $fail('CNP-ul nu e valid.');
                    }
                }],
                'id_series' => ['required', 'regex:/^[A-Za-z]{2}$/'],
                'id_number' => 'required|digits:6',
            ];
        } else {
            $rules['cui'] = 'required|string|max:20';
        }

        $data = $request->validate($rules, [
            'id_series.regex' => 'Seria are două litere (ex. RX).',
            'id_number.digits' => 'Numărul are 6 cifre.',
        ]);

        if ($type !== 'pf') {
            // Denumirea, nr. Reg. Com. și TVA vin de la ANAF, nu din formular
            $company = $this->findCompany($data['cui']);
            $data['cui'] = $company['cui'];
            $data['company_name'] = $company['company_name'];
            $data['reg_com'] = $company['reg_com'];
            $data['vat_payer'] = $company['vat_payer'];
        }

        $this->signup->saveBilling($tenant, $data);

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    public function checkSubdomain(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $subdomain = strtolower(trim((string) $request->input('subdomain')));

        return response()->json([
            'available' => $this->signup->subdomainAvailable($subdomain, $tenant->id),
            'full_domain' => $subdomain . '.' . $this->cloudflare->getBaseDomain(),
        ]);
    }

    /** Pasul 4 (opțional): site-ul. */
    public function site(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $data = $request->validate([
            'mode' => 'required|in:subdomain,own,later',
            'subdomain' => 'required_if:mode,subdomain|nullable|string|max:63',
            'domain' => ['required_if:mode,own', 'nullable', 'string', 'max:190'],
        ]);

        $choice = ['mode' => $data['mode']];
        if ($data['mode'] === 'subdomain') {
            $subdomain = strtolower(trim($data['subdomain']));
            if (! $this->signup->subdomainAvailable($subdomain, $tenant->id)) {
                throw ValidationException::withMessages(['subdomain' => 'Subdomeniul nu e disponibil.']);
            }
            $choice['subdomain'] = $subdomain;
        } elseif ($data['mode'] === 'own') {
            $domain = self::normalizeDomain($data['domain']);
            if (! $domain) {
                throw ValidationException::withMessages(['domain' => 'Domeniul nu pare corect (ex. bilete.exemplu.ro).']);
            }
            if (! $this->signup->domainAvailable($domain)) {
                throw ValidationException::withMessages(['domain' => 'Domeniul e deja folosit de alt cont.']);
            }
            $choice['domain'] = $domain;
        }

        $this->signup->saveChoice($tenant, 'site', $choice, 5);

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    /** Pasul 5 (opțional): template-ul. */
    public function template(Request $request, TemplateRegistry $templates): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $slug = $request->input('slug');
        if ($slug !== null && ! $templates->availableTo((string) $slug, $tenant->id)) {
            throw ValidationException::withMessages(['slug' => 'Template-ul nu e disponibil.']);
        }

        $this->signup->saveChoice($tenant, 'template', $slug ? ['slug' => $slug] : null, 6);

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    /** Pasul 6 (opțional): procesatorul de plăți. Cheile se pun în panou, după aprobare. */
    public function payments(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $request->validate([
            'processor' => ['nullable', Rule::in(array_keys(PaymentProcessorFactory::getAvailableProcessors()))],
        ]);

        $processor = $request->input('processor');
        $this->signup->saveChoice($tenant, 'payments', $processor ? ['processor' => $processor] : null, 7);

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    public function submit(Request $request): JsonResponse
    {
        $tenant = $this->signupTenant(requireVerified: true);
        $missing = $this->signup->submit($tenant, $request->user());
        if ($missing) {
            return response()->json(['message' => 'Mai ai pași obligatorii necompletați.', 'missing' => $missing], 422);
        }

        return response()->json(['state' => $this->state($tenant->fresh())]);
    }

    // ------------------------------------------------------------------

    private function currentTenant(): ?Tenant
    {
        $user = Auth::user();

        return $user ? Tenant::where('owner_id', $user->id)->whereNotNull('approval_status')->latest('id')->first() : null;
    }

    private function signupTenant(bool $requireVerified = false): Tenant
    {
        $tenant = $this->currentTenant();
        abort_unless($tenant && $tenant->isSignupIncomplete(), 403, 'Înscrierea nu mai poate fi modificată.');
        if ($requireVerified && ! Auth::user()->email_verified_at) {
            abort(403, 'Confirmă întâi adresa de email.');
        }

        return $tenant;
    }

    /** Ce știe pagina despre înscriere: pasul curent și datele salvate. */
    private function state(?Tenant $tenant): array
    {
        if (! $tenant) {
            return ['step' => 'account'];
        }

        $user = $tenant->owner_id ? \App\Models\User::find($tenant->owner_id) : null;
        $step = match (true) {
            $tenant->approval_status === 'rejected' => 'rejected',
            $tenant->isAwaitingApproval() => 'submitted',
            ! $user?->email_verified_at => 'verify',
            default => [2 => 'organizer', 3 => 'billing', 4 => 'site', 5 => 'template', 6 => 'payments'][(int) $tenant->onboarding_step] ?? 'review',
        };

        $choices = $tenant->signup_choices ?? [];
        $site = $choices['site'] ?? null;

        return [
            'step' => $step,
            'email' => $user?->email,
            'first_name' => $tenant->contact_first_name,
            'tenant_type' => $tenant->tenant_type?->value,
            'tenant_type_label' => $tenant->tenant_type?->label(),
            'public_name' => $tenant->onboarding_step > 2 ? $tenant->public_name : null,
            'work_method' => $tenant->work_method,
            'estimated_monthly_tickets' => $tenant->estimated_monthly_tickets,
            'legal_entity_type' => $tenant->legal_entity_type,
            'company_name' => $tenant->legal_entity_type && $tenant->legal_entity_type !== 'pf' ? $tenant->company_name : null,
            'cui' => $tenant->cui,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'county' => $tenant->state,
            'site' => $site,
            'suggested_subdomain' => $tenant->onboarding_step >= 4 && ! $site
                ? $this->signup->suggestSubdomain($tenant->public_name, $tenant->id)
                : null,
            'template' => $choices['template']['slug'] ?? null,
            'processor' => $choices['payments']['processor'] ?? null,
            'rejected_reason' => $tenant->approval_status === 'rejected' ? $tenant->rejected_reason : null,
        ];
    }

    /** Firma de la ANAF, care trebuie să existe și să fie activă. */
    private function findCompany(string $cui): array
    {
        if (! $this->anaf->isValidCui($cui)) {
            throw ValidationException::withMessages(['cui' => 'CUI-ul nu e valid.']);
        }

        $company = $this->anaf->lookupByCui($cui);
        if (! $company) {
            throw ValidationException::withMessages(['cui' => 'Nu am găsit firma la ANAF. Verifică CUI-ul sau încearcă din nou peste câteva minute.']);
        }
        if (empty($company['is_active'])) {
            throw ValidationException::withMessages(['cui' => 'Firma apare inactivă sau radiată la ANAF.']);
        }

        return [
            'cui' => (string) $company['cui'],
            'company_name' => (string) $company['company_name'],
            'reg_com' => $company['reg_com'] ?: null,
            'vat_payer' => (bool) $company['vat_payer'],
            'address' => $company['parsed_address']['street'] ?? $company['address'],
            'city' => $company['city'],
            'county' => $company['state'],
        ];
    }

    private function templateOptions(TemplateRegistry $templates): array
    {
        $demos = TenantSiteDemo::with('domain')->where('is_listed', true)->get()
            ->filter(fn ($d) => $d->domain && $d->domain->is_active)
            ->mapWithKeys(fn ($d) => [$d->template => 'https://' . $d->domain->domain]);
        $assets = rtrim((string) config('tenant-sites.assets_base'), '/');

        return collect($templates->all())
            ->filter(fn ($m, $slug) => ($m['visibility'] ?? 'public') === 'public' && $templates->availableTo($slug, null))
            ->map(fn ($m, $slug) => [
                'slug' => $slug,
                'name' => $m['name'] ?? $slug,
                'category' => $m['category'] ?? 'Altele',
                'description' => $m['description'] ?? '',
                'preview' => ! empty($m['preview_image']) ? $assets . '/tpl/' . $slug . '/' . ltrim($m['preview_image'], '/') : null,
                'demo_url' => $demos[$slug] ?? null,
            ])
            ->values()
            ->all();
    }

    private static function normalizeDomain(string $input): ?string
    {
        $host = parse_url(str_contains($input, '://') ? $input : 'https://' . trim($input), PHP_URL_HOST);
        $host = strtolower(preg_replace('/^www\./', '', (string) $host));

        return preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host) ? $host : null;
    }

    /** CNP românesc: 13 cifre, cu cifra de control. */
    private static function validCnp(string $cnp): bool
    {
        if (! preg_match('/^[1-9]\d{12}$/', $cnp)) {
            return false;
        }
        $weights = [2, 7, 9, 1, 4, 6, 3, 5, 8, 2, 7, 9];
        $sum = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $cnp[$i] * $w;
        }
        $control = $sum % 11;
        if ($control === 10) {
            $control = 1;
        }

        return $control === (int) $cnp[12];
    }
}
