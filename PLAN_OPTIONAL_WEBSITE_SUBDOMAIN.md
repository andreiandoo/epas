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

## Infrastructure Requirements for ticks.ro

### Cloudflare Setup
1. **Add ticks.ro to Cloudflare** (if not already)
2. **Create API Token** with permissions:
   - Zone: DNS: Edit
   - Zone: Zone: Read
   - Zone Resources: Include specific zone (ticks.ro)
3. **Get Zone ID** from the Cloudflare dashboard for ticks.ro
4. **Create base DNS record**:
   - Type: A
   - Name: @ (or ticks.ro)
   - Content: Your server IP address
   - Proxied: Yes
5. **Create wildcard CNAME** (optional, for instant subdomain resolution):
   - Type: CNAME
   - Name: *
   - Content: ticks.ro
   - Proxied: Yes

### Nginx/Server Configuration
The server must be configured to handle all subdomains of ticks.ro:

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name *.ticks.ro ticks.ro;

    # SSL certificates (use wildcard certificate or Let's Encrypt with DNS challenge)
    ssl_certificate /path/to/ticks.ro.crt;
    ssl_certificate_key /path/to/ticks.ro.key;

    # ... rest of Laravel config
}
```

### SSL Certificate
- Option 1: **Cloudflare Universal SSL** (free, automatic)
- Option 2: **Let's Encrypt wildcard** using DNS-01 challenge
- Option 3: **Purchased wildcard certificate** for *.ticks.ro

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
