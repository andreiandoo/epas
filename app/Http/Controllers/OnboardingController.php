<?php

namespace App\Http\Controllers;

use App\Enums\TenantType;
use App\Models\Artist;
use App\Models\Tenant;
use App\Models\TenantVerificationCode;
use App\Models\User;
use App\Models\Domain;
use App\Models\Microservice;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Jobs\GeneratePackageJob;
use App\Services\AnafService;
use App\Services\LocationService;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\ContractPdfService;
use App\Services\CloudflareService;
use App\Mail\ContractMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function __construct(
        private AnafService $anafService,
        private LocationService $locationService
    ) {}

    /**
     * Show the onboarding wizard
     */
    public function index()
    {
        if (!Session::has('onboarding')) {
            Session::put('onboarding', [
                'step' => 1,
                'data' => [],
            ]);
        }

        $step = Session::get('onboarding.step', 1);
        $data = Session::get('onboarding.data', []);

        $microservices = Microservice::active()->get();
        $romaniaCounties = $this->locationService->getStates('ro');
        $paymentProcessors = PaymentProcessorFactory::getAvailableProcessors();

        // Tenant types for step 2
        $tenantTypes = collect(TenantType::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ])->values();

        return view('onboarding.wizard', compact(
            'step', 'data', 'microservices', 'romaniaCounties',
            'paymentProcessors', 'tenantTypes'
        ));
    }

    /**
     * Step 1 - Personal Information (simplified)
     */
    public function storeStepOne(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step1'] = $request->only(['first_name', 'last_name', 'email', 'phone', 'password']);
        $onboarding['step'] = 2;
        Session::put('onboarding', $onboarding);

        return response()->json(['success' => true, 'next_step' => 2]);
    }

    /**
     * Step 2 - Account Type
     */
    public function storeStepTwo(Request $request)
    {
        $validTypes = collect(TenantType::cases())->map(fn ($t) => $t->value)->join(',');

        $rules = [
            'tenant_type' => "required|string|in:{$validTypes}",
            'entity_name' => 'required|string|max:255',
            'public_name' => 'required|string|max:255',
            'matched_artist_id' => 'nullable|integer',
        ];

        // Artist and Speaker can choose business type
        $tenantType = $request->input('tenant_type');
        if (in_array($tenantType, ['artist', 'speaker'])) {
            $rules['business_type'] = 'required|string|in:srl,pfa,persoana_fizica';
        } else {
            // For all other types, default to srl
            $request->merge(['business_type' => 'srl']);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step2'] = $request->only([
            'tenant_type', 'entity_name', 'public_name', 'matched_artist_id', 'business_type'
        ]);
        $onboarding['step'] = 3;
        Session::put('onboarding', $onboarding);

        return response()->json(['success' => true, 'next_step' => 3]);
    }

    /**
     * Step 3 - Company Details (conditional on business_type)
     */
    public function storeStepThree(Request $request)
    {
        $onboarding = Session::get('onboarding', []);
        $businessType = $onboarding['data']['step2']['business_type'] ?? 'srl';

        $rules = [
            'country' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'contact_position' => 'nullable|string|max:255',
            'payment_processor' => 'nullable|in:stripe,netopia,euplatesc,payu,unknown',
        ];

        if ($businessType !== 'persoana_fizica') {
            // SRL or PFA - require company fields
            $rules['vat_payer'] = 'required|boolean';
            $rules['cui'] = 'nullable|string|max:50';
            $rules['company_name'] = 'required|string|max:255';
            $rules['reg_com'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $fields = ['country', 'address', 'city', 'state', 'contact_position', 'payment_processor'];
        if ($businessType !== 'persoana_fizica') {
            $fields = array_merge($fields, ['vat_payer', 'cui', 'company_name', 'reg_com']);
        }

        $onboarding['data']['step3'] = $request->only($fields);
        $onboarding['step'] = 4;
        Session::put('onboarding', $onboarding);

        return response()->json(['success' => true, 'next_step' => 4]);
    }

    /**
     * Step 4 - Domain & Website
     */
    public function storeStepFour(Request $request)
    {
        $hasNoWebsite = filter_var($request->input('has_no_website'), FILTER_VALIDATE_BOOLEAN);

        if ($hasNoWebsite) {
            $validator = Validator::make($request->all(), [
                'subdomain' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/'],
                'estimated_monthly_tickets' => 'required|integer|min:0',
            ], [
                'subdomain.required' => 'Te rugăm să alegi un subdomeniu.',
                'subdomain.min' => 'Subdomeniul trebuie să aibă cel puțin 3 caractere.',
                'subdomain.regex' => 'Subdomeniul poate conține doar litere, cifre și cratimă.',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'domains' => 'required|array|min:1',
                'domains.*' => 'required|string',
                'estimated_monthly_tickets' => 'required|integer|min:0',
            ]);
        }

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step4'] = [
            'has_no_website' => $hasNoWebsite,
            'subdomain' => $hasNoWebsite ? strtolower($request->input('subdomain')) : null,
            'domains' => $hasNoWebsite ? [] : $request->input('domains'),
            'estimated_monthly_tickets' => $request->input('estimated_monthly_tickets'),
        ];
        $onboarding['step'] = 5;
        Session::put('onboarding', $onboarding);

        return response()->json(['success' => true, 'next_step' => 5]);
    }

    /**
     * Step 5 - Plan & Microservices + Complete Registration
     */
    public function storeStepFive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'work_method' => 'required|in:exclusive,mixed,reseller',
            'microservices' => 'nullable|array',
            'microservices.*' => 'exists:microservices,id',
            'locale' => 'required|in:ro,en,hu,de,fr',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $onboarding = Session::get('onboarding', []);
        $step1 = $onboarding['data']['step1'] ?? [];
        $step2 = $onboarding['data']['step2'] ?? [];
        $step3 = $onboarding['data']['step3'] ?? [];
        $step4 = $onboarding['data']['step4'] ?? [];
        $step5 = $request->only(['work_method', 'microservices', 'locale']);

        if (empty($step1) || empty($step2) || empty($step3) || empty($step4)) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please start the registration process again.',
            ], 422);
        }

        try {
            \DB::beginTransaction();

            // Create User
            $user = User::create([
                'name' => $step1['first_name'] . ' ' . $step1['last_name'],
                'email' => $step1['email'],
                'password' => Hash::make($step1['password']),
                'role' => 'tenant',
            ]);

            // Resolve domain
            $hasNoWebsite = $step4['has_no_website'] ?? false;
            $cloudflareService = app(CloudflareService::class);
            $baseDomain = $cloudflareService->getBaseDomain();

            if ($hasNoWebsite && !empty($step4['subdomain'])) {
                $parsedDomain = $step4['subdomain'] . '.' . $baseDomain;
            } else {
                $firstDomain = $step4['domains'][0] ?? '';
                $parsedDomain = parse_url($firstDomain, PHP_URL_HOST);
                if (!$parsedDomain) {
                    $parsedDomain = str_replace(['http://', 'https://', 'www.'], '', $firstDomain);
                    $parsedDomain = explode('/', $parsedDomain)[0];
                }
            }

            $paymentProcessor = $step3['payment_processor'] ?? null;
            if ($paymentProcessor === 'unknown') {
                $paymentProcessor = null;
            }

            $countryCode = $this->getCountryIsoCode($step3['country'] ?? 'Romania');
            $businessType = $step2['business_type'] ?? 'srl';

            $planMapping = [
                'exclusive' => ['plan' => '1percent', 'commission_rate' => 1.00],
                'mixed' => ['plan' => '2percent', 'commission_rate' => 2.00],
                'reseller' => ['plan' => '3percent', 'commission_rate' => 3.00],
            ];
            $planData = $planMapping[$step5['work_method']] ?? ['plan' => '2percent', 'commission_rate' => 2.00];

            // For persoana_fizica, use person's name as company_name
            $companyName = $businessType === 'persoana_fizica'
                ? $step1['first_name'] . ' ' . $step1['last_name']
                : ($step3['company_name'] ?? $step2['entity_name']);

            // Resolve TenantType enum
            $tenantType = TenantType::tryFrom($step2['tenant_type']);

            $tenant = Tenant::create([
                'name' => $companyName,
                'public_name' => $step2['public_name'],
                'owner_id' => $user->id,
                'slug' => Str::slug($step2['public_name']),
                'domain' => $parsedDomain,
                'status' => 'pending',
                'tenant_type' => $tenantType,
                'plan' => $planData['plan'],
                'locale' => $step5['locale'],
                'commission_mode' => 'included',
                'commission_rate' => $planData['commission_rate'],
                'vat_payer' => $businessType !== 'persoana_fizica' ? filter_var($step3['vat_payer'] ?? false, FILTER_VALIDATE_BOOLEAN) : false,
                'work_method' => $step5['work_method'],
                'estimated_monthly_tickets' => (int) ($step4['estimated_monthly_tickets'] ?? 0),
                'company_name' => $companyName,
                'cui' => $businessType !== 'persoana_fizica' ? ($step3['cui'] ?? null) : null,
                'reg_com' => $businessType !== 'persoana_fizica' ? ($step3['reg_com'] ?? null) : null,
                'address' => $step3['address'] ?? null,
                'city' => $step3['city'] ?? null,
                'state' => $step3['state'] ?? null,
                'country' => $countryCode,
                'contact_first_name' => $step1['first_name'],
                'contact_last_name' => $step1['last_name'],
                'contact_email' => $step1['email'],
                'contact_phone' => $step1['phone'],
                'contact_position' => $step3['contact_position'] ?? null,
                'payment_processor' => $paymentProcessor,
                'payment_processor_mode' => 'test',
                'has_own_website' => !$hasNoWebsite,
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
                'billing_starts_at' => now(),
                'billing_cycle_days' => 30,
                'settings' => [
                    'business_type' => $businessType,
                ],
            ]);

            // Create Domains (same logic as before)
            if ($hasNoWebsite && !empty($step4['subdomain'])) {
                $subdomain = $step4['subdomain'];
                $fullDomain = "{$subdomain}.{$baseDomain}";

                try {
                    $dnsRecord = $cloudflareService->createSubdomainRecord($subdomain);
                    $domain = Domain::create([
                        'tenant_id' => $tenant->id,
                        'domain' => $fullDomain,
                        'is_primary' => true,
                        'is_active' => true,
                        'is_managed_subdomain' => true,
                        'subdomain' => $subdomain,
                        'base_domain' => $baseDomain,
                        'cloudflare_record_id' => $dnsRecord['id'] ?? null,
                        'activated_at' => now(),
                    ]);
                    GeneratePackageJob::dispatch($domain);
                } catch (\Exception $e) {
                    Log::error('Failed to create managed subdomain DNS record', [
                        'tenant_id' => $tenant->id,
                        'subdomain' => $subdomain,
                        'error' => $e->getMessage(),
                    ]);
                    $domain = Domain::create([
                        'tenant_id' => $tenant->id,
                        'domain' => $fullDomain,
                        'is_primary' => true,
                        'is_active' => true,
                        'is_managed_subdomain' => true,
                        'subdomain' => $subdomain,
                        'base_domain' => $baseDomain,
                        'notes' => 'DNS record creation skipped: ' . $e->getMessage(),
                        'activated_at' => now(),
                    ]);
                    GeneratePackageJob::dispatch($domain);
                }
            } else {
                foreach ($step4['domains'] as $index => $domainUrl) {
                    $domainName = parse_url($domainUrl, PHP_URL_HOST);
                    if (!$domainName) {
                        $domainName = str_replace(['http://', 'https://', 'www.'], '', $domainUrl);
                        $domainName = explode('/', $domainName)[0];
                    }

                    $domain = Domain::create([
                        'tenant_id' => $tenant->id,
                        'domain' => $domainName,
                        'is_primary' => $index === 0,
                        'is_active' => false,
                        'is_managed_subdomain' => false,
                    ]);

                    $domain->verifications()->create([
                        'tenant_id' => $tenant->id,
                        'verification_method' => 'dns_txt',
                        'status' => 'pending',
                    ]);

                    GeneratePackageJob::dispatch($domain);
                }
            }

            // Attach Microservices
            if (!empty($step5['microservices'])) {
                foreach ($step5['microservices'] as $microserviceId) {
                    $tenant->microservices()->attach($microserviceId, [
                        'status' => 'pending',
                        'activated_at' => null,
                    ]);
                }
            }

            // Generate verification code for types that need social verification
            $verificationCode = null;
            $needsVerification = in_array($step2['tenant_type'], ['artist']);

            if ($needsVerification) {
                $vc = TenantVerificationCode::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'tenant_type' => $step2['tenant_type'],
                    'code' => TenantVerificationCode::generateCode(),
                    'entity_name' => $step2['entity_name'],
                    'matched_entity_id' => $step2['matched_artist_id'] ?? null,
                    'matched_entity_type' => !empty($step2['matched_artist_id']) ? Artist::class : null,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(30),
                    'meta' => [
                        'business_type' => $businessType,
                        'tenant_type_label' => $tenantType?->label(),
                    ],
                ]);
                $verificationCode = $vc->code;
            }

            \DB::commit();

            // Send emails (non-fatal)
            try {
                $this->sendRegistrationConfirmationEmail($user, $tenant, $step1);
            } catch (\Exception $e) {
                Log::error('Failed to send registration confirmation email', [
                    'user_id' => $user->id, 'error' => $e->getMessage(),
                ]);
            }

            if (!$hasNoWebsite) {
                try {
                    $this->sendDomainVerificationInstructionsEmail($user, $tenant, $step1);
                } catch (\Exception $e) {
                    Log::error('Failed to send domain verification email', [
                        'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $contractService = app(ContractPdfService::class);
                $contractPath = $contractService->generate($tenant);
                Mail::to($tenant->contact_email)->send(new ContractMail($tenant, $contractPath));
                $tenant->update(['contract_sent_at' => now()]);
            } catch (\Exception $e) {
                Log::error('Failed to generate or send contract', [
                    'tenant_id' => $tenant->id, 'error' => $e->getMessage(),
                ]);
            }

            Auth::login($user);
            Session::forget('onboarding');

            $response = [
                'success' => true,
                'message' => 'Înregistrarea a fost completată cu succes!',
            ];

            if ($verificationCode) {
                $response['verification_code'] = $verificationCode;
                $response['verification_message'] = "Pentru verificarea contului, trimite codul {$verificationCode} ca mesaj pe Instagram, Facebook sau TikTok către @tixello.";
            }

            if (!empty($step5['microservices'])) {
                Session::put('cart', array_map('intval', $step5['microservices']));
                $response['redirect'] = '/store/checkout';
            } else {
                $response['redirect'] = '/tenant';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            \DB::rollBack();

            Log::error('Onboarding Step 5 error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorResponse = [
                'success' => false,
                'message' => 'An error occurred during registration. Please try again.',
            ];

            if (config('app.debug')) {
                $errorResponse['error'] = $e->getMessage();
                $errorResponse['file'] = $e->getFile() . ':' . $e->getLine();
            }

            return response()->json($errorResponse, 500);
        }
    }

    /**
     * Search artists by name (for step 2 live search)
     */
    public function searchArtists(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $artists = Artist::query()
            ->where('is_active', true)
            ->where('name', 'ILIKE', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'main_image_url']);

        return response()->json([
            'results' => $artists->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'image' => $a->main_image_url,
            ]),
        ]);
    }

    /**
     * Check if email is available
     */
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'message' => 'Format email invalid']);
        }

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Această adresă de email este deja înregistrată' : 'Email disponibil'
        ]);
    }

    /**
     * Check if domain is available
     */
    public function checkDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['available' => false, 'message' => 'Domeniu invalid']);
        }

        $domainUrl = $request->domain;
        $domainName = parse_url($domainUrl, PHP_URL_HOST);
        if (!$domainName) {
            $domainName = str_replace(['http://', 'https://', 'www.'], '', $domainUrl);
            $domainName = explode('/', $domainName)[0];
        }

        $exists = Domain::where('domain', $domainName)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Acest domeniu este deja înregistrat' : 'Domeniu disponibil'
        ]);
    }

    /**
     * Check if subdomain is available
     */
    public function checkSubdomain(Request $request)
    {
        $subdomain = strtolower(trim($request->subdomain ?? ''));

        if (strlen($subdomain) < 3) {
            return response()->json(['available' => false, 'message' => 'Subdomeniul trebuie să aibă minim 3 caractere']);
        }

        if (strlen($subdomain) > 63) {
            return response()->json(['available' => false, 'message' => 'Subdomeniul nu poate avea mai mult de 63 de caractere']);
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            return response()->json(['available' => false, 'message' => 'Subdomeniul poate conține doar litere mici, cifre și cratime']);
        }

        $cloudflareService = app(CloudflareService::class);
        $baseDomain = $cloudflareService->getBaseDomain();
        $fullDomain = "{$subdomain}.{$baseDomain}";

        $reserved = $cloudflareService->getReservedSubdomains();
        if (in_array($subdomain, $reserved)) {
            return response()->json(['available' => false, 'message' => 'Acest subdomeniu este rezervat']);
        }

        $exists = Domain::where('domain', $fullDomain)
            ->orWhere(function($query) use ($subdomain, $baseDomain) {
                $query->where('subdomain', $subdomain)->where('base_domain', $baseDomain);
            })
            ->exists();

        $slugExists = Tenant::where('slug', $subdomain)->exists();

        if ($exists || $slugExists) {
            return response()->json(['available' => false, 'message' => 'Acest subdomeniu este deja folosit']);
        }

        return response()->json([
            'available' => true,
            'message' => 'Subdomeniu disponibil',
            'full_domain' => $fullDomain
        ]);
    }

    /**
     * ANAF CUI Lookup
     */
    public function lookupCui(Request $request)
    {
        $validator = Validator::make($request->all(), ['cui' => 'required|string']);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $cui = $request->input('cui');

        if (!$this->anafService->isValidCui($cui)) {
            return response()->json(['success' => false, 'message' => 'Invalid CUI format'], 422);
        }

        $companyData = $this->anafService->lookupByCui($cui);

        if (!$companyData) {
            return response()->json(['success' => false, 'message' => 'Company not found in ANAF database'], 404);
        }

        return response()->json(['success' => true, 'data' => $companyData]);
    }

    /**
     * Get cities for a country and state
     */
    public function getCities($country, $state)
    {
        $countryCode = $this->locationService->getCountryCode($country);

        if (!$countryCode) {
            return response()->json(['success' => false, 'cities' => []]);
        }

        $cities = $this->locationService->getCities($countryCode, $state);

        return response()->json(['success' => true, 'cities' => $cities]);
    }

    /**
     * Verify email
     */
    public function verify($token)
    {
        // TODO: Implement email verification logic
        return view('onboarding.verify', compact('token'));
    }

    /**
     * Convert country name to ISO 2-letter code
     */
    private function getCountryIsoCode(string $countryName): string
    {
        $mapping = [
            'Romania' => 'RO', 'United States' => 'US', 'Germany' => 'DE',
            'France' => 'FR', 'Italy' => 'IT', 'Spain' => 'ES',
            'United Kingdom' => 'GB', 'Bulgaria' => 'BG', 'Hungary' => 'HU', 'Moldova' => 'MD',
        ];

        return $mapping[$countryName] ?? 'RO';
    }

    /**
     * Send registration confirmation email
     */
    private function sendRegistrationConfirmationEmail(User $user, Tenant $tenant, array $step1): void
    {
        try {
            $template = EmailTemplate::where('event_trigger', 'registration_confirmation')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning('Registration confirmation email template not found or inactive');
                return;
            }

            $variables = [
                'first_name' => $step1['first_name'],
                'last_name' => $step1['last_name'],
                'full_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                'email' => $step1['email'],
                'public_name' => $tenant->public_name,
                'company_name' => $tenant->company_name,
                'website_url' => config('app.url'),
                'verification_link' => route('onboarding.verify', ['token' => Str::random(64)]),
            ];

            $processed = $template->processTemplate($variables);
            $settings = Setting::current();

            if (!empty($settings->brevo_api_key)) {
                $response = Http::withHeaders([
                    'api-key' => $settings->brevo_api_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $settings->company_name ?? 'Tixello',
                        'email' => $settings->email ?? 'noreply@tixello.com',
                    ],
                    'to' => [['email' => $step1['email'], 'name' => $step1['first_name'] . ' ' . $step1['last_name']]],
                    'subject' => $processed['subject'],
                    'htmlContent' => $processed['body'] . ($settings->email_footer ?? ''),
                ]);

                EmailLog::create([
                    'email_template_id' => $template->id,
                    'tenant_id' => $tenant->id,
                    'recipient_email' => $step1['email'],
                    'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                    'subject' => $processed['subject'],
                    'body' => $processed['body'] . ($settings->email_footer ?? ''),
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'sent_at' => $response->successful() ? now() : null,
                    'failed_at' => $response->successful() ? null : now(),
                    'error_message' => $response->successful() ? null : ($response->json('message') ?? 'Unknown error'),
                    'metadata' => [
                        'type' => 'registration_confirmation',
                        'sender_email' => $settings->email ?? 'noreply@tixello.com',
                        'sender_name' => $settings->company_name ?? 'Tixello',
                        'provider' => 'brevo',
                    ],
                ]);
            } else {
                Mail::html($processed['body'] . ($settings->email_footer ?? ''), function ($message) use ($step1, $processed) {
                    $message->to($step1['email'], $step1['first_name'] . ' ' . $step1['last_name'])
                        ->subject($processed['subject']);
                });

                EmailLog::create([
                    'email_template_id' => $template->id,
                    'tenant_id' => $tenant->id,
                    'recipient_email' => $step1['email'],
                    'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                    'subject' => $processed['subject'],
                    'body' => $processed['body'] . ($settings->email_footer ?? ''),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => [
                        'type' => 'registration_confirmation',
                        'sender_email' => config('mail.from.address'),
                        'sender_name' => config('mail.from.name'),
                        'provider' => 'laravel_mail',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send registration confirmation email', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send domain verification instructions email
     */
    private function sendDomainVerificationInstructionsEmail(User $user, Tenant $tenant, array $step1): void
    {
        try {
            $domains = $tenant->domains()->with('verifications')->get();

            if ($domains->isEmpty()) {
                return;
            }

            $domainsHtml = '';
            foreach ($domains as $domain) {
                $verification = $domain->verifications()->latest()->first();
                if (!$verification) {
                    continue;
                }

                $domainsHtml .= '<div style="margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">';
                $domainsHtml .= '<h3 style="margin: 0 0 15px 0; color: #333; font-size: 18px;">' . htmlspecialchars($domain->domain) . '</h3>';

                $domainsHtml .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #007bff;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #007bff; font-size: 14px;">Metoda 1: DNS TXT Record</h4>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">';
                $domainsHtml .= '<strong>Name:</strong> ' . htmlspecialchars($verification->getDnsRecordName()) . '<br>';
                $domainsHtml .= '<strong>Value:</strong> ' . htmlspecialchars($verification->getDnsRecordValue());
                $domainsHtml .= '</div></div>';

                $domainsHtml .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #28a745;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #28a745; font-size: 14px;">Metoda 2: Meta Tag HTML</h4>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; word-break: break-all;">';
                $domainsHtml .= htmlspecialchars($verification->getMetaTagHtml());
                $domainsHtml .= '</div></div>';

                $domainsHtml .= '<div style="padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #ffc107;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">Metoda 3: File Upload</h4>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">';
                $domainsHtml .= '<strong>Path:</strong> ' . htmlspecialchars($verification->getFileUploadPath()) . '<br>';
                $domainsHtml .= '<strong>Content:</strong> ' . htmlspecialchars($verification->getFileUploadContent());
                $domainsHtml .= '</div></div>';

                $domainsHtml .= '</div>';
            }

            $emailBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">'
                . '<h2 style="color: #333;">Instrucțiuni de Verificare Domeniu</h2>'
                . '<p>Salut ' . htmlspecialchars($step1['first_name']) . ',</p>'
                . '<p>Pentru a activa website-ul tău de ticketing, verifică proprietatea domeniilor:</p>'
                . $domainsHtml
                . '<p style="margin-top: 30px;">Cu respect,<br>Echipa Tixello</p></div>';

            $settings = Setting::current();

            if (!empty($settings->brevo_api_key)) {
                $response = Http::withHeaders([
                    'api-key' => $settings->brevo_api_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $settings->company_name ?? 'Tixello',
                        'email' => $settings->email ?? 'noreply@tixello.com',
                    ],
                    'to' => [['email' => $step1['email'], 'name' => $step1['first_name'] . ' ' . $step1['last_name']]],
                    'subject' => 'Instrucțiuni de Verificare Domeniu - Tixello',
                    'htmlContent' => $emailBody . ($settings->email_footer ?? ''),
                ]);

                EmailLog::create([
                    'email_template_id' => null,
                    'tenant_id' => $tenant->id,
                    'recipient_email' => $step1['email'],
                    'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                    'subject' => 'Instrucțiuni de Verificare Domeniu - Tixello',
                    'body' => $emailBody . ($settings->email_footer ?? ''),
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'sent_at' => $response->successful() ? now() : null,
                    'failed_at' => $response->successful() ? null : now(),
                    'error_message' => $response->successful() ? null : ($response->json('message') ?? 'Unknown error'),
                    'metadata' => ['type' => 'domain_verification_instructions', 'provider' => 'brevo'],
                ]);
            } else {
                Mail::html($emailBody . ($settings->email_footer ?? ''), function ($message) use ($step1) {
                    $message->to($step1['email'], $step1['first_name'] . ' ' . $step1['last_name'])
                        ->subject('Instrucțiuni de Verificare Domeniu - Tixello');
                });

                EmailLog::create([
                    'email_template_id' => null,
                    'tenant_id' => $tenant->id,
                    'recipient_email' => $step1['email'],
                    'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                    'subject' => 'Instrucțiuni de Verificare Domeniu - Tixello',
                    'body' => $emailBody . ($settings->email_footer ?? ''),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => ['type' => 'domain_verification_instructions', 'provider' => 'laravel_mail'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send domain verification instructions email', [
                'user_id' => $user->id, 'tenant_id' => $tenant->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
