# Implementation Plan: Optional Website with ticks.ro Subdomain

## Overview
Add the option "I don't have a website" in the tenant onboarding process (Step 3). When selected, tenants can choose a subdomain on `ticks.ro` that will be automatically created and activated via Cloudflare DNS API.

## Current State
- **Step 3 of onboarding** requires tenants to enter at least one domain URL
- Domains are stored in the `domains` table with verification required
- No Cloudflare integration exists currently
- Subdomains on `ticks.ro` would allow tenants without websites to use the platform immediately

---

## IMPORTANT: Will All Microservices Work for Subdomain Tenants?

### ✅ YES - Full Feature Parity

Tenants on `*.ticks.ro` subdomains have **exactly the same capabilities** as tenants with custom domains:

| Feature | How It Works | Subdomain Support |
|---------|--------------|-------------------|
| **Payment Processors** | Each tenant has their own `TenantPaymentConfig` with encrypted credentials (Stripe, Netopia, PayU, EuPlatesc) | ✅ Full support |
| **SMTP/Email** | Tenant can use custom SMTP OR fall back to core Brevo | ✅ Full support |
| **Microservices** | Per-tenant activation with individual settings (WhatsApp, eFactura, Accounting, Insurance) | ✅ Full support |
| **Theme/Branding** | Stored in tenant settings, loaded by tenant-client widget | ✅ Full support |
| **API Keys** | Per-tenant with scopes, rate limits, IP whitelist | ✅ Full support |
| **Events & Tickets** | All stored with tenant_id foreign key | ✅ Full support |

### How It Works Technically

1. **Tenant Resolution**: When someone visits `teatru.ticks.ro`:
   - Laravel routes catch the subdomain
   - Domain table lookup: `WHERE domain = 'teatru.ticks.ro'`
   - Tenant loaded via `$domain->tenant`

2. **API Requests**: Tenant-client widget makes API calls with:
   - `X-Tenant-ID` header
   - Signed requests (SHA-256)
   - All tenant-specific data returned

3. **Payment Processing**: Uses tenant's own credentials:
   ```
   TenantPaymentConfig (per tenant)
   ├── processor: stripe/netopia/payu/euplatesc
   ├── mode: test/live
   ├── credentials (encrypted): API keys, secrets
   └── is_active: true/false
   ```

4. **Email Sending**: Respects tenant's choice:
   ```
   if (tenant.use_core_smtp) → Use platform Brevo
   else → Use tenant.settings.mail (custom SMTP)
   ```

---

## CAPACITY & LIMITS ASSESSMENT

### Cloudflare Limits (ticks.ro)

| Limit | Free Plan | Pro Plan ($20/mo) |
|-------|-----------|-------------------|
| **DNS Records** | **200** (zones created after Sept 2024) or **1,000** (older zones) | 3,500 |
| **API Rate Limit** | 1,200 requests / 5 minutes | 1,200 requests / 5 minutes |
| **Traffic** | Unlimited | Unlimited |
| **SSL** | Universal (free, automatic) | Universal + Advanced |

**What This Means for You**:
- **Free Plan**: You can have **up to 200 tenant subdomains** (if zone created recently) or 1,000 (if older)
- **With Wildcard DNS**: The `*` record counts as 1 record, so you only need 2 DNS records total (@ and *)
- **API Calls**: Creating 1 subdomain = 1 API call. At 1,200/5min, you can create 240 tenants/minute (not a real concern)

> 💡 **Recommendation**: With wildcard DNS (`*` record), you don't need to create individual DNS records per subdomain. The wildcard handles all. You only use API to track/manage records if needed.

### Server Capacity (Your VPS via Ploi.io)

This depends on your VPS specs. Here's a general guide:

| VPS Specs | Tenants | Concurrent Users | Orders/Month | Notes |
|-----------|---------|------------------|--------------|-------|
| 2 CPU, 4GB RAM | 50-100 | 200-500 | 10,000 | Good for starting |
| 4 CPU, 8GB RAM | 100-300 | 500-1,500 | 50,000 | Recommended |
| 8 CPU, 16GB RAM | 300-1,000 | 1,500-5,000 | 200,000 | High traffic |
| 16 CPU, 32GB RAM | 1,000+ | 5,000+ | 500,000+ | Enterprise |

**Bottlenecks to Watch**:

1. **Database**:
   - SQLite: Good for < 50 tenants, < 100 concurrent users
   - MySQL/PostgreSQL: Required for production, handles 1000s of tenants

2. **Queue Workers**:
   - Background jobs (emails, package generation, webhooks)
   - Recommendation: 2-4 queue workers per CPU core

3. **Redis** (if enabled):
   - Session storage, caching, rate limiting
   - Reduces database load by 60-80%

4. **PHP Workers**:
   - Ploi default: 5-10 workers
   - High traffic: 20-50 workers

### Practical Limits Summary

| Component | Limit | Can Be Increased? |
|-----------|-------|-------------------|
| Cloudflare subdomains (with wildcard) | **Unlimited** | N/A - wildcard covers all |
| Cloudflare subdomains (individual records) | 200-1,000 | Upgrade to Pro ($20/mo) for 3,500 |
| Tenants on platform | **Unlimited** (DB dependent) | Yes - upgrade VPS/DB |
| Orders per month | **Unlimited** (performance dependent) | Yes - scale infrastructure |
| Concurrent users | VPS dependent | Yes - upgrade VPS or add load balancer |

### Recommended Architecture for Scale

```
Small (< 100 tenants)
├── Single VPS (4 CPU, 8GB RAM)
├── SQLite or MySQL
├── Cloudflare Free
└── Single queue worker

Medium (100-500 tenants)
├── VPS (8 CPU, 16GB RAM)
├── MySQL/PostgreSQL (separate DB server optional)
├── Redis for caching
├── Cloudflare Free/Pro
└── 4-8 queue workers

Large (500+ tenants)
├── Multiple VPS behind load balancer
├── Dedicated database server (MySQL/PostgreSQL)
├── Redis cluster
├── Cloudflare Pro/Business
├── Separate queue server
└── CDN for static assets
```

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

> **Total Time**: ~45 minutes active work + DNS propagation time (1-48 hours)

### BEFORE YOU START - Checklist

- [ ] You own the domain `ticks.ro`
- [ ] You have access to your domain registrar (to change nameservers)
- [ ] You have a Cloudflare account (free at cloudflare.com)
- [ ] You have your VPS IP address from Ploi.io (find it in: Ploi → Server → Overview → IP Address)
- [ ] You have access to Ploi.io dashboard

---

## PHASE 1: Cloudflare Setup

### Step 1.1: Create Cloudflare Account (if needed)

1. Open browser → Go to `https://dash.cloudflare.com`
2. If no account: Click **"Sign up"** → Enter email & password → Verify email
3. If have account: Click **"Log in"** → Enter credentials

---

### Step 1.2: Add Your Domain to Cloudflare

**Where you are**: Cloudflare Dashboard (https://dash.cloudflare.com)

1. **Look at the top navigation bar** → Click **"Add a site"** button (or on homepage click **"Add site"**)
2. **"Enter your site" page appears** → Type: `ticks.ro`
3. Click **"Continue"**
4. **"Select your plan" page appears** → Scroll down → Select **"Free"** (bottom option, $0/month)
5. Click **"Continue"**
6. **"Review DNS records" page appears** → Cloudflare scans existing records
   - If it finds existing records, leave them for now
7. Click **"Continue"**
8. **"Change your nameservers" page appears** → You'll see something like:
   ```
   ┌─────────────────────────────────────────┐
   │ Replace with Cloudflare's nameservers   │
   ├─────────────────────────────────────────┤
   │ Type   Value                            │
   │ NS     aria.ns.cloudflare.com           │
   │ NS     cruz.ns.cloudflare.com           │
   └─────────────────────────────────────────┘
   ```
9. **KEEP THIS PAGE OPEN** - you'll need these nameserver values
10. Open a **NEW browser tab** → Go to your domain registrar (where you bought ticks.ro)

---

### Step 1.3: Update Nameservers at Your Domain Registrar

**This step varies by registrar. Common examples:**

**If using Namecheap:**
1. Log in → Go to **"Domain List"**
2. Click **"Manage"** next to ticks.ro
3. Scroll to **"Nameservers"** section
4. Select **"Custom DNS"** from dropdown
5. Delete existing nameservers
6. Add the two Cloudflare nameservers (from Step 1.2)
7. Click the **green checkmark** to save

**If using GoDaddy:**
1. Log in → Go to **"My Products"** → **"Domains"**
2. Click **"DNS"** next to ticks.ro
3. Scroll to **"Nameservers"** → Click **"Change"**
4. Select **"Enter my own nameservers"**
5. Enter the two Cloudflare nameservers
6. Click **"Save"**

**If using other registrar:**
- Look for "DNS Settings", "Nameservers", or "DNS Management"
- Replace all nameservers with the two from Cloudflare

---

### Step 1.4: Confirm Nameserver Change in Cloudflare

1. Go back to your **Cloudflare tab** (from Step 1.2)
2. Click **"Done, check nameservers"**
3. You'll see: `"Great news! Cloudflare is now protecting your site"`
   - OR: `"Pending Nameserver Update"` (wait 5 min - 24 hours)
4. You'll receive an email when nameservers are active

> ⏳ **Wait for confirmation email before proceeding** (usually 5-30 minutes, max 48 hours)

---

### Step 1.5: Configure DNS Records (Type A Records)

**Where you are**: Cloudflare Dashboard → Your ticks.ro site

1. **In left sidebar** → Click **"DNS"**
2. **Click "Records"** (submenu under DNS)
3. You'll see the DNS Records management page

**Delete old records (if any):**
- If you see any existing **A**, **AAAA**, or **CNAME** records for `@` or `*`:
  - Click the **"Edit"** button (pencil icon) on each
  - Click **"Delete"** at the bottom

**Add Record #1 (Root Domain):**
1. Click **"+ Add record"** button (blue button, top right of records table)
2. **Type dropdown** → Select: `A`
3. **Name field** → Type: `@`
4. **IPv4 address field** → Type: `YOUR_VPS_IP` (e.g., `185.132.178.42`)
   - Find your IP in Ploi: Server → Overview → "IP Address"
5. **Proxy status** → Should show **orange cloud** (if gray, click it to turn orange)
6. **TTL** → Leave as "Auto"
7. Click **"Save"**

**Add Record #2 (Wildcard - for all subdomains):**
1. Click **"+ Add record"** button again
2. **Type dropdown** → Select: `A`
3. **Name field** → Type: `*` (asterisk - this is the wildcard)
4. **IPv4 address field** → Type: `YOUR_VPS_IP` (same IP as above)
5. **Proxy status** → Make sure it's **orange cloud**
6. **TTL** → Leave as "Auto"
7. Click **"Save"**

**Your DNS Records should now look like:**
```
┌───────┬──────┬─────────────────────┬─────────┬───────┐
│ Type  │ Name │ Content             │ Proxy   │ TTL   │
├───────┼──────┼─────────────────────┼─────────┼───────┤
│ A     │ @    │ 185.132.178.42      │ Proxied │ Auto  │
│ A     │ *    │ 185.132.178.42      │ Proxied │ Auto  │
└───────┴──────┴─────────────────────┴─────────┴───────┘
```

---

### Step 1.6: Configure SSL/TLS Settings

**Where you are**: Cloudflare Dashboard → Your ticks.ro site

1. **In left sidebar** → Click **"SSL/TLS"**
2. Click **"Overview"** (first submenu item)
3. You'll see **"SSL/TLS encryption mode"** section with 4 options:
   - Off
   - Flexible
   - Full
   - Full (strict) ✅ **SELECT THIS ONE**
4. Click on **"Full (strict)"** radio button

**Now configure Edge Certificates:**
1. **In left sidebar** → Still under **"SSL/TLS"** → Click **"Edge Certificates"**
2. Scroll down to find these toggles and enable them:

   | Setting | Action |
   |---------|--------|
   | **Always Use HTTPS** | Toggle **ON** (green) |
   | **Automatic HTTPS Rewrites** | Toggle **ON** (green) |
   | **Minimum TLS Version** | Select **TLS 1.2** |

---

### Step 1.7: Create Origin Certificate (for your server)

**Where you are**: Cloudflare Dashboard → SSL/TLS section

1. **In left sidebar** → Under **"SSL/TLS"** → Click **"Origin Server"**
2. Click **"Create Certificate"** button
3. **"Generate private key and CSR with Cloudflare"** should be selected (default)
4. **Private key type** → Select: `RSA (2048)`
5. **Hostnames** field → Should already show `*.ticks.ro` and `ticks.ro`
   - If not, type them manually (one per line)
6. **Certificate Validity** → Select: `15 years` (maximum)
7. Click **"Create"**

8. **IMPORTANT - Save these now!** You'll see two text boxes:
   ```
   ┌─────────────────────────────────────────────────────┐
   │ Origin Certificate                                  │
   │ -----BEGIN CERTIFICATE-----                         │
   │ MIIEojCCA4qgAwIBAgIUe...                            │
   │ -----END CERTIFICATE-----                           │
   └─────────────────────────────────────────────────────┘

   ┌─────────────────────────────────────────────────────┐
   │ Private Key                                         │
   │ -----BEGIN PRIVATE KEY-----                         │
   │ MIIEvgIBADANBgkqhki...                              │
   │ -----END PRIVATE KEY-----                           │
   └─────────────────────────────────────────────────────┘
   ```

9. **Copy the Origin Certificate** → Save to a text file (`ticks-cert.pem`)
10. **Copy the Private Key** → Save to a text file (`ticks-key.pem`)
11. Click **"OK"**

> ⚠️ **The private key is shown only ONCE.** If you lose it, you must create a new certificate.

---

### Step 1.8: Create API Token

**Where you are**: Cloudflare Dashboard

1. **Top right corner** → Click your **profile icon** (circle with your initial)
2. Click **"My Profile"**
3. **In left sidebar** → Click **"API Tokens"**
4. Click **"Create Token"** button
5. Scroll down to find **"Edit zone DNS"** template → Click **"Use template"**
6. **Token name** → Type: `ePas DNS Manager` (or any name you want)
7. **Permissions** → Should already show:
   - Zone - DNS - Edit
   - Zone - Zone - Read
8. **Zone Resources** section:
   - **Include** dropdown → Select: `Specific zone`
   - **Select...** dropdown → Select: `ticks.ro`
9. Click **"Continue to summary"**
10. Review the summary → Click **"Create Token"**
11. **COPY THE TOKEN NOW!** (shown only once)
    ```
    Example: Bxj7k9mNpQrStUvWxYz1234567890abcdefghijklmno
    ```
12. Save it to a secure location (password manager, secure note)
13. Click **"OK"**

---

### Step 1.9: Get Zone ID

**Where you are**: Cloudflare Dashboard

1. Click **"Websites"** in top navigation → Click on **ticks.ro**
2. **On the right sidebar**, scroll down to **"API"** section
3. You'll see:
   ```
   API
   ─────────────────────────
   Zone ID
   a1b2c3d4e5f6789012345678

   Account ID
   xyz987654321...
   ```
4. Click **"Click to copy"** next to **Zone ID**
5. Save it to a secure location

**You should now have saved:**
- [ ] API Token (from Step 1.8)
- [ ] Zone ID (from Step 1.9)
- [ ] Origin Certificate (from Step 1.7)
- [ ] Private Key (from Step 1.7)

---

## PHASE 2: Ploi.io Server Configuration

### Step 2.1: Add ticks.ro Site (or use existing)

**Where you are**: Ploi.io Dashboard (https://ploi.io)

**If ticks.ro is a NEW site:**
1. Click on your **server name** in the dashboard
2. **In left sidebar** → Click **"Sites"**
3. Click **"+ Create a new site"** button
4. Fill in:
   - **Root domain**: `ticks.ro`
   - **Project type**: `Laravel`
   - **Web directory**: `/public`
   - **PHP Version**: `8.2` (or `8.3`)
5. Click **"Add site"**
6. Wait for creation (1-2 minutes)

**If ticks.ro is your EXISTING ePas site:**
- Skip to Step 2.2

---

### Step 2.2: Add Wildcard Domain Alias

**Where you are**: Ploi.io → Your ticks.ro site

1. Click on **ticks.ro** site to open it
2. **In left sidebar** → Click **"Network"** (or **"Domains"** depending on Ploi version)
3. You'll see a section called **"Site Aliases"** or **"Domains & Aliases"**
4. In the input field, type: `*.ticks.ro`
5. Click **"Add"** (or **"+"** button)
6. You should see:
   ```
   Domains
   ─────────────────────────
   ticks.ro (primary)
   *.ticks.ro
   ```

---

### Step 2.3: Install SSL Certificate

**Where you are**: Ploi.io → Your ticks.ro site

1. **In left sidebar** → Click **"SSL"** (or **"Certificates"**)
2. Click **"Install a Custom Certificate"** (or **"Custom SSL"**)
3. You'll see two text areas:

   **Certificate field:**
   - Paste the **Origin Certificate** from Step 1.7
   - (The one starting with `-----BEGIN CERTIFICATE-----`)

   **Private Key field:**
   - Paste the **Private Key** from Step 1.7
   - (The one starting with `-----BEGIN PRIVATE KEY-----`)

4. Click **"Install Certificate"** (or **"Save"**)
5. Wait for installation (10-30 seconds)
6. You should see: `SSL Certificate installed successfully`

---

### Step 2.4: Verify Nginx Configuration

**Where you are**: Ploi.io → Your ticks.ro site

1. **In left sidebar** → Click **"Server"** (or **"Nginx"**)
2. Click **"Nginx Configuration"** (or **"Edit Nginx Config"**)
3. Look for the `server_name` line. It should include:
   ```nginx
   server_name ticks.ro *.ticks.ro;
   ```
4. **If `*.ticks.ro` is missing**, add it after `ticks.ro` (with a space)
5. Click **"Save"** (Ploi will automatically reload Nginx)

---

### Step 2.5: Add Environment Variables

**Where you are**: Ploi.io → Your ticks.ro site

1. **In left sidebar** → Click **"Environment"** (or **".env"**)
2. Scroll to the bottom of the .env file
3. Add these three lines:
   ```
   CLOUDFLARE_API_TOKEN=paste_your_token_from_step_1.8
   CLOUDFLARE_ZONE_ID=paste_your_zone_id_from_step_1.9
   CLOUDFLARE_BASE_DOMAIN=ticks.ro
   ```
4. Click **"Save"**

---

## PHASE 3: Verify Setup

### Step 3.1: Test DNS Resolution

1. Open browser → Go to: `https://dnschecker.org`
2. Enter: `ticks.ro`
3. Select: `A` record type
4. Click **"Search"**
5. You should see your VPS IP in green across all locations

6. Test wildcard:
   - Enter: `test.ticks.ro`
   - You should see the same IP

---

### Step 3.2: Test HTTPS

1. Open browser → Go to: `https://ticks.ro`
   - Should load (or show your Laravel app)
   - Should show padlock icon (HTTPS working)

2. Go to: `https://anything.ticks.ro`
   - Should show 404 or your app (not SSL error)
   - This confirms wildcard SSL is working

---

### Summary: What You Now Have Configured

| Component | Status |
|-----------|--------|
| Cloudflare nameservers | ✅ Active |
| DNS A record for @ | ✅ Points to VPS |
| DNS A record for * (wildcard) | ✅ Points to VPS |
| SSL/TLS mode | ✅ Full (strict) |
| Origin certificate | ✅ Installed on server (15 years) |
| Edge certificate | ✅ Automatic via Cloudflare |
| API Token | ✅ Saved for Laravel |
| Zone ID | ✅ Saved for Laravel |
| Nginx wildcard | ✅ Accepts *.ticks.ro |
| Environment variables | ✅ Added to .env |

---

## TROUBLESHOOTING

| Problem | Cause | Solution |
|---------|-------|----------|
| "DNS not found" for subdomain | DNS not propagated yet | Wait 5-30 minutes, check dnschecker.org |
| "Connection refused" | Nginx not accepting subdomain | Check Step 2.4 - verify `*.ticks.ro` in server_name |
| "SSL certificate error" | Origin cert not installed | Redo Step 2.3 |
| "Too many redirects" | SSL mode wrong | In Cloudflare: SSL/TLS → Set to "Full (strict)" |
| "502 Bad Gateway" | PHP not running | In Ploi: restart PHP/site |
| API token doesn't work | Wrong permissions | Create new token with "Edit zone DNS" template |

