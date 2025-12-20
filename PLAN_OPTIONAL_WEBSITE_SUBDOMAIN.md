# Implementation Plan: Optional Website with ticks.ro Subdomain

## Overview
Add the option "I don't have a website" in the tenant onboarding process (Step 3). When selected, tenants can choose a subdomain on `ticks.ro` that will be automatically created and activated via Cloudflare DNS API.

## Current State
- **Step 3 of onboarding** requires tenants to enter at least one domain URL
- Domains are stored in the `domains` table with verification required
- No Cloudflare integration exists currently
- Subdomains on `ticks.ro` would allow tenants without websites to use the platform immediately

---

## Implementation Steps

### 1. Configuration Updates

**File: `config/services.php`**
- Add Cloudflare API configuration:
```php
'cloudflare' => [
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),  // Zone ID for ticks.ro
    'base_domain' => env('CLOUDFLARE_BASE_DOMAIN', 'ticks.ro'),
],
```

**File: `.env.example`**
- Add environment variables:
```
# Cloudflare DNS Management (for ticks.ro subdomains)
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ZONE_ID=
CLOUDFLARE_BASE_DOMAIN=ticks.ro
```

---

### 2. Database Migration

**File: `database/migrations/xxxx_add_subdomain_fields_to_domains_table.php`**
```php
Schema::table('domains', function (Blueprint $table) {
    $table->boolean('is_managed_subdomain')->default(false)->after('is_primary');
    $table->string('subdomain', 63)->nullable()->after('is_managed_subdomain');
    $table->string('base_domain', 190)->nullable()->after('subdomain');
    $table->string('cloudflare_record_id', 32)->nullable()->after('base_domain');
});
```

Fields:
- `is_managed_subdomain`: True if this is a platform-managed subdomain
- `subdomain`: The subdomain part (e.g., "teatru-national")
- `base_domain`: The base domain (e.g., "ticks.ro")
- `cloudflare_record_id`: Cloudflare DNS record ID for management/deletion

---

### 3. Cloudflare Service

**File: `app/Services/CloudflareService.php`**

```php
class CloudflareService
{
    private string $apiToken;
    private string $zoneId;
    private string $baseDomain;
    private string $apiUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct()
    {
        $this->apiToken = config('services.cloudflare.api_token');
        $this->zoneId = config('services.cloudflare.zone_id');
        $this->baseDomain = config('services.cloudflare.base_domain');
    }

    /**
     * Create a DNS A record for a subdomain
     * Points to the main platform server
     */
    public function createSubdomainRecord(string $subdomain): array
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->apiUrl}/zones/{$this->zoneId}/dns_records", [
                'type' => 'CNAME',
                'name' => $subdomain,
                'content' => $this->baseDomain,  // CNAME to base domain
                'ttl' => 1,  // Auto TTL
                'proxied' => true,  // Enable Cloudflare proxy
            ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to create DNS record: " . $response->body());
        }

        return $response->json('result');
    }

    /**
     * Delete a DNS record
     */
    public function deleteSubdomainRecord(string $recordId): bool
    {
        $response = Http::withToken($this->apiToken)
            ->delete("{$this->apiUrl}/zones/{$this->zoneId}/dns_records/{$recordId}");

        return $response->successful();
    }

    /**
     * Check if a subdomain already exists
     */
    public function subdomainExists(string $subdomain): bool
    {
        $response = Http::withToken($this->apiToken)
            ->get("{$this->apiUrl}/zones/{$this->zoneId}/dns_records", [
                'type' => 'CNAME',
                'name' => "{$subdomain}.{$this->baseDomain}",
            ]);

        if (!$response->successful()) {
            return false;
        }

        return count($response->json('result', [])) > 0;
    }

    public function getBaseDomain(): string
    {
        return $this->baseDomain;
    }
}
```

---

### 4. Frontend Changes (wizard.blade.php - Step 3)

**Add to `formData`:**
```javascript
// Step 3
domains: [''],
no_website: false,  // NEW
subdomain: '',      // NEW
estimated_monthly_tickets: '',
```

**Add UI for "No website" option:**
```html
<!-- No Website Option -->
<div class="mb-6">
    <label class="flex items-center cursor-pointer">
        <input
            type="checkbox"
            x-model="formData.no_website"
            @change="if(formData.no_website) { formData.domains = []; formData.subdomain = ''; } else { formData.domains = ['']; }"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        >
        <span class="ml-3 text-sm text-gray-700">
            Nu am un website propriu - vreau un subdomeniu pe ticks.ro
        </span>
    </label>
    <p class="text-xs text-gray-500 mt-1 ml-6">
        Vei primi un subdomeniu gratuit care va fi activat automat (ex: teatrul-tau.ticks.ro)
    </p>
</div>

<!-- Subdomain Input (shown when no_website is checked) -->
<div class="mb-6" x-show="formData.no_website" x-cloak>
    <label class="block text-sm font-medium text-gray-700 mb-2">Alege subdomeniul tău *</label>
    <div class="flex items-center">
        <input
            type="text"
            x-model="formData.subdomain"
            @input.debounce.500ms="checkSubdomainAvailability()"
            class="flex-1 px-4 py-2 border rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            :class="subdomainError ? 'border-red-500' : (subdomainAvailable ? 'border-green-500' : 'border-gray-300')"
            placeholder="numele-tau"
            pattern="[a-z0-9-]+"
            :required="formData.no_website"
        >
        <span class="px-4 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600">
            .ticks.ro
        </span>
    </div>
    <p class="text-xs text-gray-500 mt-1">
        Doar litere mici, cifre și cratime. Minim 3 caractere.
    </p>
    <span x-show="subdomainError" class="text-red-500 text-sm" x-text="subdomainError"></span>
    <span x-show="subdomainAvailable && !subdomainError && formData.subdomain.length >= 3" class="text-green-500 text-sm">
        ✓ Subdomeniul este disponibil
    </span>
</div>

<!-- Domain URLs (hidden when no_website is checked) -->
<div class="mb-6" x-show="!formData.no_website">
    <!-- existing domain input code -->
</div>
```

**Add subdomain validation function:**
```javascript
subdomainError: '',
subdomainAvailable: false,
subdomainChecking: false,

async checkSubdomainAvailability() {
    const subdomain = this.formData.subdomain.toLowerCase().trim();

    // Reset state
    this.subdomainError = '';
    this.subdomainAvailable = false;

    // Validate format
    if (subdomain.length < 3) {
        this.subdomainError = 'Subdomeniul trebuie să aibă minim 3 caractere';
        return;
    }

    if (subdomain.length > 63) {
        this.subdomainError = 'Subdomeniul nu poate avea mai mult de 63 de caractere';
        return;
    }

    if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/.test(subdomain)) {
        this.subdomainError = 'Subdomeniul poate conține doar litere mici, cifre și cratime (nu poate începe sau termina cu cratimă)';
        return;
    }

    // Reserved subdomains
    const reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'cdn', 'static', 'assets', 'test', 'demo', 'staging', 'dev', 'core', 'panel'];
    if (reserved.includes(subdomain)) {
        this.subdomainError = 'Acest subdomeniu este rezervat';
        return;
    }

    this.subdomainChecking = true;

    try {
        const response = await fetch('/register/check-subdomain', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ subdomain: subdomain })
        });

        const data = await response.json();
        this.subdomainAvailable = data.available;
        if (!data.available) {
            this.subdomainError = data.message || 'Subdomeniul nu este disponibil';
        }
    } catch (error) {
        console.error('Error checking subdomain:', error);
        this.subdomainError = 'Eroare la verificare';
    } finally {
        this.subdomainChecking = false;
    }
}
```

**Update `submitStep3()` validation:**
```javascript
async submitStep3() {
    this.loading = true;
    this.errors = {};

    // Validate based on mode
    if (this.formData.no_website) {
        if (!this.formData.subdomain || this.formData.subdomain.length < 3) {
            this.openModal('Eroare', 'Te rugăm să alegi un subdomeniu valid', 'error');
            this.loading = false;
            return;
        }
        if (!this.subdomainAvailable) {
            this.openModal('Eroare', 'Subdomeniul nu este disponibil', 'error');
            this.loading = false;
            return;
        }
    } else {
        if (!this.formData.domains.length || !this.formData.domains[0]) {
            this.openModal('Eroare', 'Te rugăm să adaugi cel puțin un domeniu', 'error');
            this.loading = false;
            return;
        }
    }

    // ... rest of submit logic
}
```

---

### 5. Backend Controller Updates

**File: `app/Http/Controllers/OnboardingController.php`**

**Add new method `checkSubdomain()`:**
```php
/**
 * Check if subdomain is available
 */
public function checkSubdomain(Request $request)
{
    $validator = Validator::make($request->all(), [
        'subdomain' => 'required|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'available' => false,
            'message' => 'Format subdomeniu invalid'
        ]);
    }

    $subdomain = strtolower($request->subdomain);
    $baseDomain = config('services.cloudflare.base_domain', 'ticks.ro');
    $fullDomain = "{$subdomain}.{$baseDomain}";

    // Check reserved subdomains
    $reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'cdn', 'static', 'assets', 'test', 'demo', 'staging', 'dev', 'core', 'panel'];
    if (in_array($subdomain, $reserved)) {
        return response()->json([
            'available' => false,
            'message' => 'Acest subdomeniu este rezervat'
        ]);
    }

    // Check if already exists in database
    $exists = Domain::where('domain', $fullDomain)
        ->orWhere(function($query) use ($subdomain, $baseDomain) {
            $query->where('subdomain', $subdomain)
                  ->where('base_domain', $baseDomain);
        })
        ->exists();

    if ($exists) {
        return response()->json([
            'available' => false,
            'message' => 'Acest subdomeniu este deja folosit'
        ]);
    }

    return response()->json([
        'available' => true,
        'message' => 'Subdomeniu disponibil',
        'full_domain' => $fullDomain
    ]);
}
```

**Update `storeStepThree()`:**
```php
public function storeStepThree(Request $request)
{
    $noWebsite = filter_var($request->no_website, FILTER_VALIDATE_BOOLEAN);

    if ($noWebsite) {
        // Validate subdomain
        $validator = Validator::make($request->all(), [
            'subdomain' => 'required|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/',
            'estimated_monthly_tickets' => 'required|integer|min:0',
        ]);
    } else {
        // Validate domains
        $validator = Validator::make($request->all(), [
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string',
            'estimated_monthly_tickets' => 'required|integer|min:0',
        ]);
    }

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Store in session
    $onboarding = Session::get('onboarding', []);
    $onboarding['data']['step3'] = [
        'no_website' => $noWebsite,
        'subdomain' => $noWebsite ? strtolower($request->subdomain) : null,
        'domains' => $noWebsite ? [] : $request->domains,
        'estimated_monthly_tickets' => $request->estimated_monthly_tickets,
    ];
    $onboarding['step'] = 4;
    Session::put('onboarding', $onboarding);

    return response()->json([
        'success' => true,
        'next_step' => 4
    ]);
}
```

**Update `storeStepFour()` - Domain creation section:**
```php
// In storeStepFour(), after tenant creation, replace the domain creation loop:

// Create Domains
$noWebsite = $step3['no_website'] ?? false;

if ($noWebsite && !empty($step3['subdomain'])) {
    // Create managed subdomain
    $cloudflareService = app(CloudflareService::class);
    $baseDomain = $cloudflareService->getBaseDomain();
    $subdomain = $step3['subdomain'];
    $fullDomain = "{$subdomain}.{$baseDomain}";

    try {
        // Create DNS record in Cloudflare
        $dnsRecord = $cloudflareService->createSubdomainRecord($subdomain);

        // Create domain record - auto-activated since it's managed
        $domain = Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'is_primary' => true,
            'is_active' => true,  // Auto-activate managed subdomains
            'is_managed_subdomain' => true,
            'subdomain' => $subdomain,
            'base_domain' => $baseDomain,
            'cloudflare_record_id' => $dnsRecord['id'] ?? null,
            'activated_at' => now(),
        ]);

        // Generate deployment package for this domain
        GeneratePackageJob::dispatch($domain);

        Log::info('Managed subdomain created', [
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'cloudflare_record_id' => $dnsRecord['id'] ?? null,
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to create managed subdomain', [
            'tenant_id' => $tenant->id,
            'subdomain' => $subdomain,
            'error' => $e->getMessage(),
        ]);

        // Still create domain record but mark as inactive
        $domain = Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'is_primary' => true,
            'is_active' => false,
            'is_managed_subdomain' => true,
            'subdomain' => $subdomain,
            'base_domain' => $baseDomain,
            'notes' => 'Failed to create DNS record: ' . $e->getMessage(),
        ]);

        // Create verification so they can still verify manually if needed
        $domain->verifications()->create([
            'tenant_id' => $tenant->id,
            'verification_method' => 'dns_txt',
            'status' => 'pending',
        ]);
    }
} else {
    // Original domain creation flow for custom domains
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
            'is_active' => false,
            'is_managed_subdomain' => false,
        ]);

        // Create verification entry for the domain
        $domain->verifications()->create([
            'tenant_id' => $tenant->id,
            'verification_method' => 'dns_txt',
            'status' => 'pending',
        ]);

        // Generate deployment package
        GeneratePackageJob::dispatch($domain);
    }
}
```

---

### 6. Route Registration

**File: `routes/web.php`**

Add the new subdomain check route:
```php
Route::post('/register/check-subdomain', [OnboardingController::class, 'checkSubdomain'])
    ->name('onboarding.check-subdomain');
```

---

### 7. Domain Model Updates

**File: `app/Models/Domain.php`**

Add new fields to `$fillable`:
```php
protected $fillable = [
    'tenant_id',
    'domain',
    'is_active',
    'is_suspended',
    'is_primary',
    'is_managed_subdomain',  // NEW
    'subdomain',             // NEW
    'base_domain',           // NEW
    'cloudflare_record_id',  // NEW
    'activated_at',
    'suspended_at',
    'notes',
];
```

Add helper methods:
```php
/**
 * Check if this is a managed subdomain
 */
public function isManagedSubdomain(): bool
{
    return $this->is_managed_subdomain === true;
}

/**
 * Get the full domain name
 */
public function getFullDomain(): string
{
    if ($this->is_managed_subdomain && $this->subdomain && $this->base_domain) {
        return "{$this->subdomain}.{$this->base_domain}";
    }
    return $this->domain;
}

/**
 * Scope for managed subdomains
 */
public function scopeManagedSubdomains($query)
{
    return $query->where('is_managed_subdomain', true);
}
```

---

### 8. Tenant Client CORS Updates

**File: `app/Http/Middleware/TenantClientCors.php`**

Update to handle managed subdomains:
```php
// In the subdomain matching section, also check managed subdomains:
if (!$domain) {
    $parts = explode('.', $originHost);
    if (count($parts) >= 2) {
        // Check if it's a managed subdomain
        $baseDomain = config('services.cloudflare.base_domain', 'ticks.ro');
        if (str_ends_with($originHost, ".{$baseDomain}")) {
            $domain = Domain::where('domain', $originHost)
                ->where('is_managed_subdomain', true)
                ->where('is_active', true)
                ->first();
        }

        // Fallback to original subdomain logic
        if (!$domain && count($parts) > 2) {
            $baseDomainParts = implode('.', array_slice($parts, -2));
            $domain = Domain::where('domain', $baseDomainParts)
                ->where('is_active', true)
                ->first();
        }
    }
}
```

---

## STEP-BY-STEP SETUP GUIDE FOR ticks.ro (Ploi.io + Cloudflare)

### PHASE 1: Domain & DNS Setup in Cloudflare

#### Step 1.1: Add ticks.ro to Cloudflare
1. Go to https://dash.cloudflare.com
2. Click "Add a Site" → Enter `ticks.ro`
3. Choose the **Free** plan (sufficient for this)
4. Cloudflare will scan existing DNS records
5. **Update nameservers** at your domain registrar to Cloudflare's nameservers:
   - Example: `nova.ns.cloudflare.com` and `rick.ns.cloudflare.com`
   - Wait 24-48 hours for propagation (usually much faster)

#### Step 1.2: Configure DNS Records in Cloudflare
Go to **DNS** → **Records** and add:

| Type | Name | Content | Proxy | TTL |
|------|------|---------|-------|-----|
| A | @ | `YOUR_VPS_IP` | ✅ Proxied | Auto |
| A | * | `YOUR_VPS_IP` | ✅ Proxied | Auto |

> ⚠️ **Important**: The wildcard `*` record ensures ALL subdomains (like `teatru.ticks.ro`) point to your server. With proxy enabled, Cloudflare handles SSL automatically.

#### Step 1.3: SSL/TLS Configuration in Cloudflare
Go to **SSL/TLS** → **Overview**:
1. Set encryption mode to **Full (strict)** ← Recommended
2. Go to **Edge Certificates** → Enable:
   - Always Use HTTPS: ✅ ON
   - Automatic HTTPS Rewrites: ✅ ON

> ✅ **HTTPS Answer**: Yes! All subdomains will have HTTPS automatically. Cloudflare provides free Universal SSL that covers `*.ticks.ro` and `ticks.ro`. No certificate purchase needed.

#### Step 1.4: Create Cloudflare API Token
1. Go to **My Profile** (top right) → **API Tokens**
2. Click **Create Token**
3. Use template: **Edit zone DNS**
4. Configure permissions:
   ```
   Zone - DNS - Edit
   Zone - Zone - Read
   ```
5. Zone Resources: **Include** → **Specific zone** → Select `ticks.ro`
6. Click **Continue to summary** → **Create Token**
7. **COPY THE TOKEN** (shown only once!)

#### Step 1.5: Get Zone ID
1. Go to **ticks.ro** dashboard in Cloudflare
2. Scroll down on the right sidebar → **API** section
3. Copy the **Zone ID** (32-character string)

---

### PHASE 2: Ploi.io Server Configuration

#### Step 2.1: Add ticks.ro Site to Ploi
1. Go to your server in Ploi.io
2. Click **Sites** → **New Site**
3. Configure:
   - **Root Domain**: `ticks.ro`
   - **Web Directory**: `/public` (Laravel default)
   - **PHP Version**: 8.2 or 8.3
4. **Important**: This should point to your ePas Laravel application

#### Step 2.2: Configure Wildcard Subdomain in Ploi
1. Go to the `ticks.ro` site in Ploi
2. Click **Domains & Aliases**
3. Add alias: `*.ticks.ro` (wildcard)
4. Click **Add Alias**

> This tells Nginx to accept requests for ALL subdomains of ticks.ro

#### Step 2.3: SSL Certificate in Ploi
Since Cloudflare handles SSL at the edge, you have two options:

**Option A: Cloudflare Origin Certificate (Recommended)**
1. In Cloudflare: **SSL/TLS** → **Origin Server** → **Create Certificate**
2. Choose:
   - Private key type: RSA (2048)
   - Hostnames: `*.ticks.ro, ticks.ro`
   - Validity: 15 years
3. Copy the **Origin Certificate** and **Private Key**
4. In Ploi: Site → **SSL** → **Install Custom Certificate**
5. Paste certificate and key

**Option B: Let's Encrypt (requires DNS challenge)**
1. In Ploi: Site → **SSL** → **Let's Encrypt**
2. Enable **DNS Challenge**
3. Add Cloudflare API token for automatic DNS validation
4. Request wildcard: `*.ticks.ro`

> ⚠️ With Cloudflare proxying, self-signed certificates also work, but origin certificates are cleaner.

#### Step 2.4: Verify Nginx Configuration
Ploi automatically generates Nginx config. Verify it includes:
```nginx
server_name ticks.ro *.ticks.ro;
```

If you need to customize, go to Site → **Nginx Configuration**.

---

### PHASE 3: Laravel Application Configuration

#### Step 3.1: Environment Variables
Add to your `.env` file on the server:
```env
# Cloudflare DNS Management (for ticks.ro subdomains)
CLOUDFLARE_API_TOKEN=your_api_token_from_step_1.4
CLOUDFLARE_ZONE_ID=your_zone_id_from_step_1.5
CLOUDFLARE_BASE_DOMAIN=ticks.ro
```

In Ploi: Site → **Environment** → Add these variables.

#### Step 3.2: Deploy the Code
After implementing the code changes, deploy via Ploi:
1. Site → **Repository** → Connect to your git repo
2. Set deploy branch to `core-main` (or your production branch)
3. Click **Deploy**

Or trigger deployment via webhook/CI.

---

### PHASE 4: How Tenant Websites Work on Subdomains

#### Current System (Custom Domains)
1. Tenant registers with their domain (e.g., `teatrul-national.ro`)
2. They download a "deployment package" (HTML + JS widget)
3. They install it on THEIR server
4. Widget connects to ePas API

#### New System (Managed Subdomains)
For managed subdomains like `teatru.ticks.ro`:

**The tenant's "website" is hosted on YOUR server automatically!**

We will serve a full HTML page from Laravel that:
1. Loads the tenant-client widget
2. Displays their events, ticketing, etc.
3. Uses their theme/branding from the database

No separate deployment needed - it's all served from the ePas platform.

---

### ADDITIONAL: Tenant Website Routes (Auto-Deployment)

**File: `routes/web.php`** - Add subdomain routing:
```php
// Managed subdomain routes - serves tenant websites
Route::domain('{subdomain}.' . config('services.cloudflare.base_domain', 'ticks.ro'))
    ->middleware(['web'])
    ->group(function () {
        Route::get('/', [TenantWebsiteController::class, 'index']);
        Route::get('/{any}', [TenantWebsiteController::class, 'index'])->where('any', '.*');
    });
```

**File: `app/Http/Controllers/TenantWebsiteController.php`** (New):
```php
<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantWebsiteController extends Controller
{
    public function index(Request $request, string $subdomain)
    {
        $baseDomain = config('services.cloudflare.base_domain', 'ticks.ro');
        $fullDomain = "{$subdomain}.{$baseDomain}";

        // Find the tenant by subdomain
        $domain = Domain::where('domain', $fullDomain)
            ->where('is_managed_subdomain', true)
            ->where('is_active', true)
            ->with('tenant')
            ->first();

        if (!$domain || !$domain->tenant) {
            abort(404, 'Website not found');
        }

        $tenant = $domain->tenant;

        // Serve the tenant website template
        return view('tenant-website.index', [
            'tenant' => $tenant,
            'domain' => $domain,
            'apiUrl' => config('services.tenant_client.api_url'),
        ]);
    }
}
```

**File: `resources/views/tenant-website/index.blade.php`** (New):
```html
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'ro' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->public_name }} - Bilete</title>
    <meta name="description" content="Cumpără bilete pentru evenimentele organizate de {{ $tenant->public_name }}">

    <!-- Tenant Client Widget -->
    <script>
        window.TIXELLO_CONFIG = {
            apiUrl: '{{ $apiUrl }}',
            tenantId: '{{ $tenant->id }}',
            domain: '{{ $domain->domain }}',
            locale: '{{ $tenant->locale ?? "ro" }}'
        };
    </script>
</head>
<body>
    <!-- The widget will render here -->
    <div id="tixello-app"></div>

    <!-- Load the tenant client widget -->
    <script src="{{ $apiUrl }}/tenant-client/tixello-loader.iife.js"></script>
</body>
</html>
```

---

### Summary: What Happens Automatically

1. **Tenant registers** → Chooses "I don't have a website" → Picks `teatru.ticks.ro`
2. **System creates DNS record** in Cloudflare via API (instant)
3. **Cloudflare propagates** the DNS (seconds with wildcard, minutes otherwise)
4. **SSL is automatic** via Cloudflare Universal SSL
5. **Tenant visits** `https://teatru.ticks.ro` → Works immediately!
6. **Laravel serves** their personalized ticket shop with their events
7. **Admin can manage** everything from the admin panel - no manual deployment

### What You (Admin) Can Do

| Action | How |
|--------|-----|
| View all managed subdomains | Admin → Tenants → Filter by "Managed Subdomain" |
| Deactivate a subdomain | Admin → Domains → Toggle Active |
| Delete subdomain (removes DNS) | Admin → Domains → Delete (calls Cloudflare API) |
| See subdomain status | Domain record shows `cloudflare_record_id` |

---

### Verification Checklist (After Setup)

- [ ] ticks.ro loads correctly (main site or redirect)
- [ ] Create a test subdomain manually in Cloudflare (e.g., test.ticks.ro)
- [ ] test.ticks.ro resolves and shows HTTPS
- [ ] API token can list DNS records (test with `curl`)
- [ ] Wildcard alias works in Ploi (check Nginx config)
- [ ] Deploy code and test onboarding flow

---

## Email Notification Updates

For managed subdomains, skip the domain verification email since they're auto-activated:

```php
// In storeStepFour(), update email sending:
if (!$noWebsite) {
    // Only send domain verification instructions for custom domains
    try {
        $this->sendDomainVerificationInstructionsEmail($user, $tenant, $step1);
    } catch (\Exception $e) {
        Log::error('Failed to send domain verification email', [...]);
    }
}
```

---

## Testing Checklist

1. [ ] New tenant selects "I don't have a website"
2. [ ] Subdomain input appears and domain input hides
3. [ ] Subdomain validation works (format, length, reserved words)
4. [ ] Subdomain availability check works
5. [ ] Registration completes successfully
6. [ ] DNS record created in Cloudflare
7. [ ] Domain marked as active in database
8. [ ] Tenant can access their subdomain immediately
9. [ ] Tenant client (widget) works on the subdomain
10. [ ] No verification email sent for managed subdomains
11. [ ] Fallback works if Cloudflare API fails

---

## Rollback Plan

If issues occur:
1. Set `CLOUDFLARE_API_TOKEN=` (empty) to disable Cloudflare integration
2. Subdomains will be created but not auto-activated
3. Manual DNS record creation and domain activation via admin panel

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `config/services.php` | Add Cloudflare config |
| `.env.example` | Add Cloudflare env vars |
| `database/migrations/xxxx_add_subdomain_fields_to_domains_table.php` | Create |
| `app/Services/CloudflareService.php` | Create |
| `app/Models/Domain.php` | Add new fields and methods |
| `app/Http/Controllers/OnboardingController.php` | Add checkSubdomain, update storeStepThree/Four |
| `resources/views/onboarding/wizard.blade.php` | Add subdomain UI |
| `routes/web.php` | Add check-subdomain route |
| `app/Http/Middleware/TenantClientCors.php` | Handle managed subdomains |
