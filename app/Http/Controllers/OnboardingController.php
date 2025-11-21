<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Domain;
use App\Models\Microservice;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Services\AnafService;
use App\Services\LocationService;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
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
                    'verification_method' => 'pending',
                    'status' => 'pending',
                ]);
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

            // Send registration confirmation email
            $this->sendRegistrationConfirmationEmail($user, $tenant, $step1);

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
                    'message' => 'Registration completed! Redirecting to checkout...',
                    'redirect' => '/checkout'
                ]);
            }

            // No microservices selected, redirect to verify page
            return response()->json([
                'success' => true,
                'message' => 'Registration completed successfully! Please check your email to verify your account.',
                'redirect' => route('onboarding.verify', ['token' => 'pending'])
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();

            Log::error('Onboarding Step 4 error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'step1' => $step1,
                'step2' => $step2,
                'step3' => $step3,
                'step4' => $step4,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during registration. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error'
            ], 500);
        }
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
                'verification_link' => config('app.url') . '/verify/' . Str::random(64),
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
}
