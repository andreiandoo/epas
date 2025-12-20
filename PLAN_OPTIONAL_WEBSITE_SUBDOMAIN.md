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

### BEFORE YOU START - Checklist

- [ ] You own the domain `ticks.ro`
- [ ] You have access to your domain registrar (to change nameservers)
- [ ] You have a Cloudflare account (free at cloudflare.com)
- [ ] You have your VPS IP address from Ploi.io
- [ ] You have access to Ploi.io dashboard

---

### PHASE 1: Domain & DNS Setup in Cloudflare

#### Step 1.1: Add ticks.ro to Cloudflare

**Time: 5 minutes | Propagation: 1-48 hours**

1. Go to https://dash.cloudflare.com
2. Click the blue **"Add a Site"** button (or "Add site" in top nav)
3. Enter `ticks.ro` in the domain field
4. Click **"Add site"**
5. Select the **Free** plan → Click **"Continue"**
6. Cloudflare will scan for existing DNS records - Click **"Continue"**
7. You'll see two nameservers like:
   ```
   Type    Nameserver
   NS      aria.ns.cloudflare.com
   NS      cruz.ns.cloudflare.com
   ```
8. **Go to your domain registrar** (where you bought ticks.ro) and:
   - Find DNS/Nameserver settings
   - Replace existing nameservers with Cloudflare's
   - Save changes
9. Back in Cloudflare, click **"Done, check nameservers"**
10. Wait for email confirmation (usually 5 min - 24 hours)

> 💡 **Tip**: You can check propagation at https://dnschecker.org

---

#### Step 1.2: Configure DNS Records in Cloudflare

**Time: 2 minutes**

Once nameservers are active, go to **DNS** → **Records**:

**Delete** any existing A/AAAA/CNAME records for @ and * (if any)

**Add these 2 records:**

| Step | Type | Name | Content | Proxy | TTL |
|------|------|------|---------|-------|-----|
| 1 | A | `@` | `YOUR_VPS_IP` (e.g., `123.45.67.89`) | ✅ Proxied (orange cloud) | Auto |
| 2 | A | `*` | `YOUR_VPS_IP` (e.g., `123.45.67.89`) | ✅ Proxied (orange cloud) | Auto |

**How to add:**
1. Click **"Add record"**
2. Type: Select `A`
3. Name: Enter `@` (for root) or `*` (for wildcard)
4. IPv4 address: Enter your VPS IP
5. Proxy status: Click the cloud to make it **orange** (proxied)
6. Click **"Save"**
7. Repeat for the second record

> 🔑 **Key Point**: The wildcard `*` record handles ALL subdomains automatically. You don't need to add individual records for each tenant. This means **unlimited subdomains** with just 2 DNS records!

---

#### Step 1.3: SSL/TLS Configuration in Cloudflare

**Time: 2 minutes**

1. Go to **SSL/TLS** in the left sidebar
2. Click **Overview**
3. Select **"Full (strict)"** encryption mode
4. Go to **SSL/TLS** → **Edge Certificates**
5. Enable these settings:
   - **Always Use HTTPS**: Toggle ON
   - **Automatic HTTPS Rewrites**: Toggle ON
   - **Minimum TLS Version**: TLS 1.2

> ✅ **HTTPS is now automatic** for `ticks.ro` and ALL subdomains (`*.ticks.ro`)

---

#### Step 1.4: Create Cloudflare API Token

**Time: 3 minutes**

1. Click your profile icon (top right) → **"My Profile"**
2. Go to **"API Tokens"** tab
3. Click **"Create Token"**
4. Find **"Edit zone DNS"** template → Click **"Use template"**
5. Configure:
   - **Token name**: `ePas ticks.ro DNS Manager` (or any name)
   - **Permissions**: Already set correctly (Zone - DNS - Edit)
   - **Zone Resources**:
     - Include → Specific zone → Select `ticks.ro`
6. Click **"Continue to summary"**
7. Click **"Create Token"**
8. **⚠️ IMPORTANT: Copy the token NOW** (shown only once!)
   ```
   Example: Bxj7k9mNpQrStUvWxYz1234567890abcdefg
   ```
9. Store it securely (you'll add it to `.env` later)

---

#### Step 1.5: Get Zone ID

**Time: 1 minute**

1. Go back to `ticks.ro` dashboard in Cloudflare
2. On the right sidebar, scroll down to **"API"** section
3. Copy the **Zone ID** (32-character string)
   ```
   Example: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
   ```
4. Store it securely (you'll add it to `.env` later)

---

### PHASE 2: Ploi.io Server Configuration

#### Step 2.1: Add ticks.ro Site to Ploi

**Time: 5 minutes**

1. Log in to https://ploi.io
2. Go to your server
3. Click **"Sites"** in the left menu
4. Click **"New Site"** button
5. Configure:
   ```
   Root Domain: ticks.ro
   Project Type: Laravel
   Web Directory: /public
   PHP Version: 8.2 (or 8.3)
   ```
6. Click **"Add Site"**
7. Wait for site creation (1-2 minutes)

**If ticks.ro is your EXISTING ePas site:**
- Skip creating a new site
- Just add the alias in the next step

---

#### Step 2.2: Configure Wildcard Subdomain in Ploi

**Time: 2 minutes**

1. Go to the `ticks.ro` site in Ploi
2. Click **"Manage"** (or enter site settings)
3. Go to **"Domains & Aliases"** (or just "Domains")
4. In the **"Add Alias"** section:
   ```
   Domain: *.ticks.ro
   ```
5. Click **"Add Alias"**

> ✅ Now Nginx will accept requests for ALL subdomains of ticks.ro

---

#### Step 2.3: SSL Certificate in Ploi

**Time: 5 minutes**

Since Cloudflare handles SSL at the edge, we need an **Origin Certificate**:

**In Cloudflare:**
1. Go to **SSL/TLS** → **Origin Server**
2. Click **"Create Certificate"**
3. Configure:
   - Generate private key and CSR: **Cloudflare** (selected by default)
   - Private key type: **RSA (2048)**
   - Hostnames: Enter both:
     ```
     *.ticks.ro
     ticks.ro
     ```
   - Validity: **15 years** (max)
4. Click **"Create"**
5. You'll see two text boxes:
   - **Origin Certificate** (starts with `-----BEGIN CERTIFICATE-----`)
   - **Private Key** (starts with `-----BEGIN PRIVATE KEY-----`)
6. **Copy both** (keep this page open!)

**In Ploi:**
1. Go to your `ticks.ro` site
2. Click **"SSL"** (or "Certificates")
3. Click **"Install Custom Certificate"** (or "Custom SSL")
4. Paste:
   - **Certificate**: The Origin Certificate from Cloudflare
   - **Private Key**: The Private Key from Cloudflare
5. Click **"Install Certificate"**

> ✅ Your server now has a valid SSL certificate for `*.ticks.ro`

---

#### Step 2.4: Verify Nginx Configuration

**Time: 1 minute**

1. In Ploi, go to your `ticks.ro` site
2. Click **"Nginx Configuration"** (or "Server Config")
3. Look for the `server_name` line - it should include:
   ```nginx
   server_name ticks.ro *.ticks.ro;
   ```
4. If not present, add `*.ticks.ro` after `ticks.ro`
5. Click **"Save"** (Ploi will reload Nginx automatically)

---

### PHASE 3: Laravel Application Configuration

#### Step 3.1: Environment Variables

**Time: 2 minutes**

**In Ploi:**
1. Go to your `ticks.ro` site
2. Click **"Environment"** (or ".env")
3. Add these lines at the end:
   ```env
   # Cloudflare DNS Management (for ticks.ro subdomains)
   CLOUDFLARE_API_TOKEN=your_token_from_step_1.4
   CLOUDFLARE_ZONE_ID=your_zone_id_from_step_1.5
   CLOUDFLARE_BASE_DOMAIN=ticks.ro
   ```
4. Click **"Save"**

---

#### Step 3.2: Deploy the Code

**Time: 5 minutes**

1. In Ploi, go to **"Repository"** (or "Deployment")
2. If not connected:
   - Connect to your Git provider (GitHub/GitLab/Bitbucket)
   - Select your ePas repository
   - Set branch: `core-main` (or your production branch)
3. Click **"Deploy Now"**
4. Wait for deployment to complete
5. Run migrations (if not in deploy script):
   ```bash
   php artisan migrate --force
   ```

---

#### Step 3.3: Verify Everything Works

**Time: 5 minutes**

1. Visit `https://ticks.ro` - Should load your main site
2. Visit `https://test.ticks.ro` - Should show 404 (no tenant yet)
3. Check Nginx logs for errors:
   - In Ploi: Site → Logs → Nginx
4. Check Laravel logs:
   - In Ploi: Site → Logs → Laravel

---

### PHASE 4: Test the Complete Flow

**Time: 10 minutes**

1. **Create a test tenant:**
   - Go through onboarding at `https://ticks.ro/register`
   - At Step 3, check "Nu am un website propriu"
   - Enter subdomain: `test-tenant`
   - Complete registration

2. **Verify subdomain works:**
   - Visit `https://test-tenant.ticks.ro`
   - Should see tenant's ticket shop

3. **Check database:**
   - Domain record created with `is_managed_subdomain = true`
   - `cloudflare_record_id` populated (if not using wildcard-only mode)

4. **Check Cloudflare (optional):**
   - DNS records page should show the record (if creating individual records)

---

### TROUBLESHOOTING

| Problem | Solution |
|---------|----------|
| Subdomain shows "DNS not found" | Wait 5 mins for DNS propagation, or check wildcard record exists |
| Subdomain shows connection refused | Check Nginx has `*.ticks.ro` in server_name |
| HTTPS certificate error | Check origin certificate is installed in Ploi |
| 404 on subdomain | Check Laravel routes are configured for subdomain |
| 500 error on subdomain | Check Laravel logs: `storage/logs/laravel.log` |

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
