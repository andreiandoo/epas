<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
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
        // Initialize session data if not exists
        if (!Session::has('onboarding')) {
            Session::put('onboarding', [
                'step' => 1,
                'data' => [],
            ]);
        }

        $step = Session::get('onboarding.step', 1);
        $data = Session::get('onboarding.data', []);

        // Get microservices for step 4
        $microservices = Microservice::active()->get();

        // Get Romanian counties for step 2
        $romaniaCounties = $this->locationService->getStates('ro');

        // Get available payment processors for step 2
        $paymentProcessors = PaymentProcessorFactory::getAvailableProcessors();

        return view('onboarding.wizard', compact('step', 'data', 'microservices', 'romaniaCounties', 'paymentProcessors'));
    }

    /**
     * Store Step 1 - Personal Information
     */
    public function storeStepOne(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'public_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'contact_position' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Store in session
        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step1'] = $request->only(['first_name', 'last_name', 'public_name', 'email', 'phone', 'contact_position', 'password']);
        $onboarding['step'] = 2;
        Session::put('onboarding', $onboarding);

        return response()->json([
            'success' => true,
            'next_step' => 2
        ]);
    }

    /**
     * Store Step 2 - Company Information
     */
    public function storeStepTwo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string|max:255',
            'vat_payer' => 'required|boolean',
            'cui' => 'nullable|string|max:50',
            'company_name' => 'required|string|max:255',
            'reg_com' => 'nullable|string|max:255',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'payment_processor' => 'required|in:stripe,netopia,euplatesc,payu',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Store in session
        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step2'] = $request->only([
            'country', 'vat_payer', 'cui', 'company_name', 'reg_com', 'address', 'city', 'state', 'payment_processor'
        ]);
        $onboarding['step'] = 3;
        Session::put('onboarding', $onboarding);

        return response()->json([
            'success' => true,
            'next_step' => 3
        ]);
    }

    /**
     * Store Step 3 - Websites
     */
    public function storeStepThree(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string',
            'estimated_monthly_tickets' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Store in session
        $onboarding = Session::get('onboarding', []);
        $onboarding['data']['step3'] = $request->only(['domains', 'estimated_monthly_tickets']);
        $onboarding['step'] = 4;
        Session::put('onboarding', $onboarding);

        return response()->json([
            'success' => true,
            'next_step' => 4
        ]);
    }

    /**
     * Store Step 4 - Work Method and Complete Registration
     */
    public function storeStepFour(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'work_method' => 'required|in:exclusive,mixed,reseller',
            'microservices' => 'nullable|array',
            'microservices.*' => 'exists:microservices,id',
            'locale' => 'required|in:ro,en,hu,de,fr',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Get all onboarding data
        $onboarding = Session::get('onboarding', []);
        $step1 = $onboarding['data']['step1'] ?? [];
        $step2 = $onboarding['data']['step2'] ?? [];
        $step3 = $onboarding['data']['step3'] ?? [];
        $step4 = $request->only(['work_method', 'microservices', 'locale']);

        // Validate we have all required data
        if (empty($step1) || empty($step2) || empty($step3)) {
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
                'role' => 'tenant', // Tenant owner role
            ]);

            // Extract domain from first URL
            $firstDomain = $step3['domains'][0];
            $parsedDomain = parse_url($firstDomain, PHP_URL_HOST);
            if (!$parsedDomain) {
                // If parse_url fails, try to extract domain manually
                $parsedDomain = str_replace(['http://', 'https://', 'www.'], '', $firstDomain);
                $parsedDomain = explode('/', $parsedDomain)[0];
            }

            // Convert country name to ISO code
            $countryCode = $this->getCountryIsoCode($step2['country'] ?? 'Romania');

            // Map work_method to plan and commission_rate
            $planMapping = [
                'exclusive' => ['plan' => '1percent', 'commission_rate' => 1.00],
                'mixed' => ['plan' => '2percent', 'commission_rate' => 2.00],
                'reseller' => ['plan' => '3percent', 'commission_rate' => 3.00],
            ];
            $planData = $planMapping[$step4['work_method']] ?? ['plan' => '2percent', 'commission_rate' => 2.00];

            // Create Tenant
            $tenant = Tenant::create([
                'name' => $step2['company_name'],
                'public_name' => $step1['public_name'],
                'owner_id' => $user->id,
                'slug' => Str::slug($step1['public_name']),
                'domain' => $parsedDomain,
                'status' => 'pending', // Pending until email verification
                'plan' => $planData['plan'],
                'locale' => $step4['locale'],
                'commission_mode' => 'included',
                'commission_rate' => $planData['commission_rate'],
                'vat_payer' => filter_var($step2['vat_payer'], FILTER_VALIDATE_BOOLEAN),
                'work_method' => $step4['work_method'],
                'estimated_monthly_tickets' => (int)$step3['estimated_monthly_tickets'],
                'company_name' => $step2['company_name'],
                'cui' => $step2['cui'] ?? null,
                'reg_com' => $step2['reg_com'] ?? null,
                'address' => $step2['address'] ?? null,
                'city' => $step2['city'] ?? null,
                'state' => $step2['state'] ?? null,
                'country' => $countryCode,
                'contact_first_name' => $step1['first_name'],
                'contact_last_name' => $step1['last_name'],
                'contact_email' => $step1['email'],
                'contact_phone' => $step1['phone'],
                'contact_position' => $step1['contact_position'] ?? null,
                'payment_processor' => $step2['payment_processor'] ?? null,
                'payment_processor_mode' => 'test', // Start in test mode
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
                'billing_starts_at' => now(),
                'billing_cycle_days' => 30,
            ]);

            // Create Domains
            foreach ($step3['domains'] as $index => $domainUrl) {
                $domainName = parse_url($domainUrl, PHP_URL_HOST);
                if (!$domainName) {
                    $domainName = str_replace(['http://', 'https://', 'www.'], '', $domainUrl);
                    $domainName = explode('/', $domainName)[0];
                }

                $domain = Domain::create([
                    'tenant_id' => $tenant->id,
                    'domain' => $domainName,
                    'is_primary' => $index === 0,
                    'is_active' => false, // Activate after email verification
                ]);

                // Create verification entry for the domain
                $domain->verifications()->create([
                    'tenant_id' => $tenant->id,
                    'verification_method' => 'dns_txt',
                    'status' => 'pending',
                ]);

                // Generate deployment package for this domain
                GeneratePackageJob::dispatch($domain);
            }

            // Attach Microservices
            if (!empty($step4['microservices'])) {
                foreach ($step4['microservices'] as $microserviceId) {
                    $tenant->microservices()->attach($microserviceId, [
                        'is_active' => false, // Activate after payment/verification
                        'activated_at' => null,
                    ]);
                }
            }

            \DB::commit();

            // Send emails (non-fatal - registration continues even if emails fail)
            try {
                $this->sendRegistrationConfirmationEmail($user, $tenant, $step1);
            } catch (\Exception $e) {
                Log::error('Failed to send registration confirmation email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $this->sendDomainVerificationInstructionsEmail($user, $tenant, $step1);
            } catch (\Exception $e) {
                Log::error('Failed to send domain verification email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Generate and send contract (non-fatal - registration continues even if contract fails)
            try {
                $contractService = app(ContractPdfService::class);
                $contractPath = $contractService->generate($tenant);

                // Send contract email
                Mail::to($tenant->contact_email)
                    ->send(new ContractMail($tenant, $contractPath));

                $tenant->update(['contract_sent_at' => now()]);

                Log::info('Contract generated and sent', [
                    'tenant_id' => $tenant->id,
                    'contract_path' => $contractPath,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to generate or send contract', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log in the user
            Auth::login($user);

            // Clear onboarding session
            Session::forget('onboarding');

            // If microservices were selected, add to cart and redirect to checkout
            if (!empty($step4['microservices'])) {
                // Store microservice IDs in cart session (same format as store cart)
                Session::put('cart', array_map('intval', $step4['microservices']));

                return response()->json([
                    'success' => true,
                    'message' => 'Înregistrarea a fost completată cu succes! Vei fi redirecționat către checkout...',
                    'redirect' => '/store/checkout'
                ]);
            }

            // No microservices selected, redirect to tenant panel
            return response()->json([
                'success' => true,
                'message' => 'Înregistrarea a fost completată cu succes! Verifică-ți email-ul pentru instrucțiunile de verificare a domeniului.',
                'redirect' => '/tenant'
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();

            Log::error('Onboarding Step 4 error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'step1' => $step1,
                'step2' => $step2,
                'step3' => $step3,
                'step4' => $step4,
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
     * Check if email is available
     */
    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Format email invalid'
            ]);
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
            return response()->json([
                'available' => false,
                'message' => 'Domeniu invalid'
            ]);
        }

        // Parse the domain from URL
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
     * ANAF CUI Lookup
     */
    public function lookupCui(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cui' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cui = $request->input('cui');

        // Validate CUI format
        if (!$this->anafService->isValidCui($cui)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CUI format'
            ], 422);
        }

        $companyData = $this->anafService->lookupByCui($cui);

        if (!$companyData) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found in ANAF database'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $companyData
        ]);
    }

    /**
     * Get cities for a country and state (API endpoint for dynamic loading)
     */
    public function getCities($country, $state)
    {
        $countryCode = $this->locationService->getCountryCode($country);

        if (!$countryCode) {
            return response()->json([
                'success' => false,
                'cities' => []
            ]);
        }

        $cities = $this->locationService->getCities($countryCode, $state);

        return response()->json([
            'success' => true,
            'cities' => $cities
        ]);
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
            'Romania' => 'RO',
            'United States' => 'US',
            'Germany' => 'DE',
            'France' => 'FR',
            'Italy' => 'IT',
            'Spain' => 'ES',
            'United Kingdom' => 'GB',
            'Bulgaria' => 'BG',
            'Hungary' => 'HU',
            'Moldova' => 'MD',
        ];

        return $mapping[$countryName] ?? 'RO';
    }

    /**
     * Send registration confirmation email
     */
    private function sendRegistrationConfirmationEmail(User $user, Tenant $tenant, array $step1): void
    {
        try {
            // Find the registration confirmation template
            $template = EmailTemplate::where('event_trigger', 'registration_confirmation')
                ->where('is_active', true)
                ->first();

            if (!$template) {
                Log::warning('Registration confirmation email template not found or inactive');
                return;
            }

            // Prepare variables for the template
            $variables = [
                'first_name' => $step1['first_name'],
                'last_name' => $step1['last_name'],
                'full_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                'email' => $step1['email'],
                'public_name' => $step1['public_name'],
                'company_name' => $tenant->company_name,
                'website_url' => config('app.url'),
                'verification_link' => route('onboarding.verify', ['token' => Str::random(64)]),
            ];

            // Process template
            $processed = $template->processTemplate($variables);

            // Get settings for email sending
            $settings = Setting::current();

            if (!empty($settings->brevo_api_key)) {
                // Send via Brevo API
                $response = Http::withHeaders([
                    'api-key' => $settings->brevo_api_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $settings->company_name ?? 'Tixello',
                        'email' => $settings->email ?? 'noreply@tixello.com',
                    ],
                    'to' => [
                        ['email' => $step1['email'], 'name' => $step1['first_name'] . ' ' . $step1['last_name']]
                    ],
                    'subject' => $processed['subject'],
                    'htmlContent' => $processed['body'] . ($settings->email_footer ?? ''),
                ]);

                // Log the email
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
                // Fallback to Laravel mail
                Mail::html($processed['body'] . ($settings->email_footer ?? ''), function ($message) use ($step1, $processed) {
                    $message->to($step1['email'], $step1['first_name'] . ' ' . $step1['last_name'])
                        ->subject($processed['subject']);
                });

                // Log the email
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
            // Get all domains with their verification codes
            $domains = $tenant->domains()->with('verifications')->get();

            if ($domains->isEmpty()) {
                return;
            }

            // Build the email content
            $domainsHtml = '';
            foreach ($domains as $domain) {
                $verification = $domain->verifications()->latest()->first();
                if (!$verification) {
                    continue;
                }

                $domainsHtml .= '<div style="margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">';
                $domainsHtml .= '<h3 style="margin: 0 0 15px 0; color: #333; font-size: 18px;">🌐 ' . htmlspecialchars($domain->domain) . '</h3>';

                // Method 1: DNS TXT Record
                $domainsHtml .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #007bff;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #007bff; font-size: 14px;">Metoda 1: Înregistrare DNS TXT</h4>';
                $domainsHtml .= '<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">Adaugă o înregistrare TXT în setările DNS ale domeniului tău:</p>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">';
                $domainsHtml .= '<strong>Name/Host:</strong> ' . htmlspecialchars($verification->getDnsRecordName()) . '<br>';
                $domainsHtml .= '<strong>Value/Content:</strong> ' . htmlspecialchars($verification->getDnsRecordValue());
                $domainsHtml .= '</div>';
                $domainsHtml .= '<p style="margin: 10px 0 0 0; color: #888; font-size: 11px;">💡 Modificările DNS pot dura până la 24-48 ore să se propage.</p>';
                $domainsHtml .= '</div>';

                // Method 2: Meta Tag
                $domainsHtml .= '<div style="margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #28a745;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #28a745; font-size: 14px;">Metoda 2: Meta Tag HTML</h4>';
                $domainsHtml .= '<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">Adaugă acest meta tag în secțiunea &lt;head&gt; a paginii principale:</p>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; word-break: break-all;">';
                $domainsHtml .= htmlspecialchars($verification->getMetaTagHtml());
                $domainsHtml .= '</div>';
                $domainsHtml .= '<p style="margin: 10px 0 0 0; color: #888; font-size: 11px;">💡 Pune tag-ul imediat după &lt;head&gt; sau înainte de &lt;/head&gt;.</p>';
                $domainsHtml .= '</div>';

                // Method 3: File Upload
                $domainsHtml .= '<div style="padding: 15px; background-color: #fff; border-radius: 6px; border-left: 4px solid #ffc107;">';
                $domainsHtml .= '<h4 style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">Metoda 3: Fișier de Verificare</h4>';
                $domainsHtml .= '<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">Creează un fișier pe serverul tău la această locație:</p>';
                $domainsHtml .= '<div style="background-color: #f1f3f4; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">';
                $domainsHtml .= '<strong>Cale fișier:</strong> ' . htmlspecialchars($verification->getFileUploadPath()) . '<br>';
                $domainsHtml .= '<strong>Conținut fișier:</strong> ' . htmlspecialchars($verification->getFileUploadContent());
                $domainsHtml .= '</div>';
                $domainsHtml .= '<p style="margin: 10px 0 0 0; color: #888; font-size: 11px;">💡 Fișierul trebuie să fie accesibil la: https://' . htmlspecialchars($domain->domain) . $verification->getFileUploadPath() . '</p>';
                $domainsHtml .= '</div>';

                $domainsHtml .= '</div>';
            }

            // Build full email body
            $emailBody = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">Instrucțiuni de Verificare Domeniu</h2>

                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    Salut ' . htmlspecialchars($step1['first_name']) . ',
                </p>

                <p style="color: #555; font-size: 14px; line-height: 1.6;">
                    Pentru a activa website-ul tău de ticketing, trebuie să verifici proprietatea domeniilor înregistrate.
                    <strong>Alege una dintre cele 3 metode</strong> pentru fiecare domeniu:
                </p>

                ' . $domainsHtml . '

                <div style="margin-top: 30px; padding: 15px; background-color: #e7f3ff; border-radius: 8px; border: 1px solid #b8daff;">
                    <h4 style="margin: 0 0 10px 0; color: #004085; font-size: 14px;">⏱️ Informații Importante</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #004085; font-size: 13px;">
                        <li>Codul de verificare expiră în <strong>7 zile</strong></li>
                        <li>Alege <strong>o singură metodă</strong> pentru fiecare domeniu</li>
                        <li>După ce ai adăugat codul, domeniul va fi verificat automat sau manual de echipa noastră</li>
                        <li>Vei primi un email de confirmare când domeniul este activat</li>
                    </ul>
                </div>

                <div style="margin-top: 30px; padding: 15px; background-color: #fff3cd; border-radius: 8px; border: 1px solid #ffc107;">
                    <h4 style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">🔧 Ai nevoie de ajutor?</h4>
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        Dacă nu ești sigur cum să adaugi aceste coduri, contactează-ne sau trimite instrucțiunile administratorului tău web/IT.
                    </p>
                </div>

                <p style="margin-top: 30px; color: #555; font-size: 14px;">
                    Cu respect,<br>
                    Echipa Tixello
                </p>
            </div>';

            // Get settings for email sending
            $settings = Setting::current();

            if (!empty($settings->brevo_api_key)) {
                // Send via Brevo API
                $response = Http::withHeaders([
                    'api-key' => $settings->brevo_api_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $settings->company_name ?? 'Tixello',
                        'email' => $settings->email ?? 'noreply@tixello.com',
                    ],
                    'to' => [
                        ['email' => $step1['email'], 'name' => $step1['first_name'] . ' ' . $step1['last_name']]
                    ],
                    'subject' => 'Instrucțiuni de Verificare Domeniu - Tixello',
                    'htmlContent' => $emailBody . ($settings->email_footer ?? ''),
                ]);

                // Log the email
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
                    'metadata' => [
                        'type' => 'domain_verification_instructions',
                        'sender_email' => $settings->email ?? 'noreply@tixello.com',
                        'sender_name' => $settings->company_name ?? 'Tixello',
                        'provider' => 'brevo',
                        'domains_count' => $domains->count(),
                    ],
                ]);
            } else {
                // Fallback to Laravel mail
                Mail::html($emailBody . ($settings->email_footer ?? ''), function ($message) use ($step1) {
                    $message->to($step1['email'], $step1['first_name'] . ' ' . $step1['last_name'])
                        ->subject('Instrucțiuni de Verificare Domeniu - Tixello');
                });

                // Log the email
                EmailLog::create([
                    'email_template_id' => null,
                    'tenant_id' => $tenant->id,
                    'recipient_email' => $step1['email'],
                    'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
                    'subject' => 'Instrucțiuni de Verificare Domeniu - Tixello',
                    'body' => $emailBody . ($settings->email_footer ?? ''),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => [
                        'type' => 'domain_verification_instructions',
                        'sender_email' => config('mail.from.address'),
                        'sender_name' => config('mail.from.name'),
                        'provider' => 'laravel_mail',
                        'domains_count' => $domains->count(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send domain verification instructions email', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
