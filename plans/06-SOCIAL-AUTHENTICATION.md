# Social Authentication (OAuth) Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently, users must create accounts with email and password. This creates:
1. **Registration friction**: Users abandon sign-up due to password requirements
2. **Password fatigue**: Users must remember yet another password
3. **Lower conversion**: Studies show social login increases conversion by 20-50%
4. **Trust concerns**: Users may hesitate to share email with unknown platforms
5. **No profile data**: Manual entry of name, email, profile picture

### What This Feature Does
Implements OAuth authentication with major providers:
- **Google**: Most widely used, high trust
- **Facebook**: Large user base, event discovery synergy
- **Apple**: Required for iOS apps, privacy-focused users
- Allows linking multiple social accounts to one profile
- Auto-fills profile information from social provider
- Works for both admin Users and tenant Customers

---

## Technical Implementation

### 1. Package Installation

```bash
composer require laravel/socialite
```

### 2. Database Migrations

Create `database/migrations/2026_01_03_000020_create_social_accounts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable'); // user_id + user_type OR customer_id + customer_type
            $table->string('provider'); // google, facebook, apple
            $table->string('provider_id');
            $table->string('provider_token', 1000)->nullable();
            $table->string('provider_refresh_token', 1000)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['authenticatable_type', 'authenticatable_id']);
        });

        // Add social login fields to customers
        Schema::table('customers', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email');
            $table->boolean('is_social_login')->default(false)->after('avatar_url');
            $table->boolean('password_set')->default(true)->after('is_social_login');
        });

        // Add to users if needed
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'is_social_login', 'password_set']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
        });
    }
};
```

### 3. Configuration

Update `config/services.php`:

```php
<?php

return [
    // ... existing config

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', '/auth/apple/callback'),
    ],
];
```

Create `config/social-auth.php`:

```php
<?php

return [
    'enabled_providers' => explode(',', env('SOCIAL_AUTH_PROVIDERS', 'google,facebook,apple')),

    'providers' => [
        'google' => [
            'name' => 'Google',
            'icon' => 'google',
            'color' => '#4285F4',
            'scopes' => ['openid', 'profile', 'email'],
        ],
        'facebook' => [
            'name' => 'Facebook',
            'icon' => 'facebook',
            'color' => '#1877F2',
            'scopes' => ['email', 'public_profile'],
        ],
        'apple' => [
            'name' => 'Apple',
            'icon' => 'apple',
            'color' => '#000000',
            'scopes' => ['name', 'email'],
        ],
    ],

    'auto_link_by_email' => env('SOCIAL_AUTH_AUTO_LINK', true),
    'allow_unlink_last' => env('SOCIAL_AUTH_ALLOW_UNLINK_LAST', false),
];
```

Update `.env.example`:

```
# Social Authentication
SOCIAL_AUTH_PROVIDERS=google,facebook,apple
SOCIAL_AUTH_AUTO_LINK=true

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=/auth/google/callback

# Facebook OAuth
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=/auth/facebook/callback

# Apple Sign In
APPLE_CLIENT_ID=
APPLE_CLIENT_SECRET=
APPLE_REDIRECT_URI=/auth/apple/callback
```

### 4. Model

Create `app/Models/SocialAccount.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialAccount extends Model
{
    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'token_expires_at',
        'email',
        'name',
        'avatar',
        'provider_data',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'provider_data' => 'array',
    ];

    protected $hidden = [
        'provider_token',
        'provider_refresh_token',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }
}
```

Add trait to User and Customer models:

Create `app/Models/Traits/HasSocialAccounts.php`:

```php
<?php

namespace App\Models\Traits;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSocialAccounts
{
    public function socialAccounts(): MorphMany
    {
        return $this->morphMany(SocialAccount::class, 'authenticatable');
    }

    public function getSocialAccount(string $provider): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', $provider)->first();
    }

    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    public function getLinkedProviders(): array
    {
        return $this->socialAccounts()->pluck('provider')->toArray();
    }

    public function canUnlinkSocialAccount(): bool
    {
        // Can unlink if has password or more than one social account
        if ($this->password_set ?? true) {
            return true;
        }

        return $this->socialAccounts()->count() > 1;
    }
}
```

Update `app/Models/Customer.php`:

```php
use App\Models\Traits\HasSocialAccounts;

class Customer extends Model
{
    use HasSocialAccounts;

    // ... existing code
}
```

Update `app/Models/User.php`:

```php
use App\Models\Traits\HasSocialAccounts;

class User extends Authenticatable
{
    use HasSocialAccounts;

    // ... existing code
}
```

### 5. Service Class

Create `app/Services/Auth/SocialAuthService.php`:

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Customer;
use App\Models\SocialAccount;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialAuthService
{
    /**
     * Get the redirect URL for a provider
     */
    public function getRedirectUrl(string $provider, array $params = []): string
    {
        $driver = Socialite::driver($provider);

        // Add scopes if configured
        $scopes = config("social-auth.providers.{$provider}.scopes", []);
        if (!empty($scopes)) {
            $driver->scopes($scopes);
        }

        // Handle stateless for API
        if (!empty($params['stateless'])) {
            $driver->stateless();
        }

        // Add state parameter for tenant context
        if (!empty($params['tenant_id'])) {
            $driver->with(['state' => encrypt(['tenant_id' => $params['tenant_id']])]);
        }

        return $driver->redirect()->getTargetUrl();
    }

    /**
     * Handle the callback from a provider
     */
    public function handleCallback(string $provider, ?string $state = null): array
    {
        $driver = Socialite::driver($provider);

        // Stateless for API callbacks
        $socialUser = $driver->stateless()->user();

        // Decrypt state if present
        $tenantId = null;
        if ($state) {
            try {
                $decoded = decrypt($state);
                $tenantId = $decoded['tenant_id'] ?? null;
            } catch (\Exception $e) {
                // Invalid state
            }
        }

        return [
            'social_user' => $socialUser,
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * Find or create a customer from social login
     */
    public function findOrCreateCustomer(
        SocialiteUser $socialUser,
        string $provider,
        int $tenantId
    ): Customer {
        // Check if social account exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->where('authenticatable_type', Customer::class)
            ->first();

        if ($socialAccount) {
            $customer = $socialAccount->authenticatable;
            $this->updateSocialAccount($socialAccount, $socialUser);
            return $customer;
        }

        // Check if customer exists by email (auto-link)
        $email = $socialUser->getEmail();
        if ($email && config('social-auth.auto_link_by_email')) {
            $customer = Customer::where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($customer) {
                $this->linkSocialAccount($customer, $socialUser, $provider);
                return $customer;
            }
        }

        // Create new customer
        return $this->createCustomerFromSocial($socialUser, $provider, $tenantId);
    }

    /**
     * Find or create a user (admin) from social login
     */
    public function findOrCreateUser(SocialiteUser $socialUser, string $provider): User
    {
        // Check if social account exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->where('authenticatable_type', User::class)
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->authenticatable;
            $this->updateSocialAccount($socialAccount, $socialUser);
            return $user;
        }

        // Check if user exists by email
        $email = $socialUser->getEmail();
        if ($email && config('social-auth.auto_link_by_email')) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $this->linkSocialAccount($user, $socialUser, $provider);
                return $user;
            }
        }

        // Create new user
        return $this->createUserFromSocial($socialUser, $provider);
    }

    /**
     * Link a social account to an existing user/customer
     */
    public function linkSocialAccount(
        User|Customer $user,
        SocialiteUser $socialUser,
        string $provider
    ): SocialAccount {
        // Check if already linked
        if ($user->hasSocialAccount($provider)) {
            throw new \Exception("Account already linked to {$provider}");
        }

        return SocialAccount::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_token' => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'avatar' => $socialUser->getAvatar(),
            'provider_data' => $socialUser->getRaw(),
        ]);
    }

    /**
     * Unlink a social account
     */
    public function unlinkSocialAccount(User|Customer $user, string $provider): bool
    {
        if (!$user->canUnlinkSocialAccount()) {
            throw new \Exception('Cannot unlink the last authentication method. Please set a password first.');
        }

        $socialAccount = $user->getSocialAccount($provider);

        if (!$socialAccount) {
            throw new \Exception("Account not linked to {$provider}");
        }

        return $socialAccount->delete();
    }

    /**
     * Create a new customer from social data
     */
    protected function createCustomerFromSocial(
        SocialiteUser $socialUser,
        string $provider,
        int $tenantId
    ): Customer {
        $name = $socialUser->getName() ?? '';
        $nameParts = explode(' ', $name, 2);

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'email' => $socialUser->getEmail(),
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'avatar_url' => $socialUser->getAvatar(),
            'is_social_login' => true,
            'password_set' => false,
            'password' => Hash::make(Str::random(32)), // Random password
            'email_verified_at' => now(), // Social emails are considered verified
        ]);

        $this->linkSocialAccount($customer, $socialUser, $provider);

        return $customer;
    }

    /**
     * Create a new user from social data
     */
    protected function createUserFromSocial(SocialiteUser $socialUser, string $provider): User
    {
        $user = User::create([
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar_url' => $socialUser->getAvatar(),
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
        ]);

        $this->linkSocialAccount($user, $socialUser, $provider);

        return $user;
    }

    /**
     * Update existing social account with fresh data
     */
    protected function updateSocialAccount(SocialAccount $account, SocialiteUser $socialUser): void
    {
        $account->update([
            'provider_token' => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
            'avatar' => $socialUser->getAvatar(),
            'provider_data' => $socialUser->getRaw(),
        ]);

        // Update user avatar if changed
        $user = $account->authenticatable;
        if ($user && $socialUser->getAvatar() && $user->avatar_url !== $socialUser->getAvatar()) {
            $user->avatar_url = $socialUser->getAvatar();
            $user->save();
        }
    }

    /**
     * Get enabled providers
     */
    public function getEnabledProviders(): array
    {
        $enabled = config('social-auth.enabled_providers', []);
        $providers = config('social-auth.providers', []);

        return array_filter($providers, fn($key) => in_array($key, $enabled), ARRAY_FILTER_USE_KEY);
    }
}
```

### 6. Controllers

Create `app/Http/Controllers/Auth/SocialAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    /**
     * Get available social providers
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => $this->socialAuthService->getEnabledProviders(),
        ]);
    }

    /**
     * Redirect to provider
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $url = $this->socialAuthService->getRedirectUrl($provider);

        return redirect($url);
    }

    /**
     * Handle provider callback
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $result = $this->socialAuthService->handleCallback($provider, $request->query('state'));
            $user = $this->socialAuthService->findOrCreateUser($result['social_user'], $provider);

            Auth::login($user);

            return redirect('/admin/dashboard')->with('success', 'Logged in successfully!');

        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Link a social account (authenticated users)
     */
    public function link(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $user = $request->user();

        if ($user->hasSocialAccount($provider)) {
            return back()->with('error', "Already linked to {$provider}");
        }

        // Store that we're linking, then redirect
        session(['social_linking' => true]);

        $url = $this->socialAuthService->getRedirectUrl($provider);

        return redirect($url);
    }

    /**
     * Handle link callback
     */
    public function linkCallback(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        if (!session('social_linking')) {
            return redirect('/profile')->with('error', 'Invalid request');
        }

        session()->forget('social_linking');

        try {
            $result = $this->socialAuthService->handleCallback($provider);
            $this->socialAuthService->linkSocialAccount(
                $request->user(),
                $result['social_user'],
                $provider
            );

            return redirect('/profile/security')->with('success', "{$provider} account linked!");

        } catch (\Exception $e) {
            return redirect('/profile/security')->with('error', $e->getMessage());
        }
    }

    /**
     * Unlink a social account
     */
    public function unlink(Request $request, string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        try {
            $this->socialAuthService->unlinkSocialAccount($request->user(), $provider);

            return response()->json(['message' => "{$provider} account unlinked"]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    protected function validateProvider(string $provider): void
    {
        $enabled = config('social-auth.enabled_providers', []);

        if (!in_array($provider, $enabled)) {
            abort(404, 'Provider not supported');
        }
    }
}
```

Create `app/Http/Controllers/Api/TenantClient/SocialAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    /**
     * Get available social providers
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => $this->socialAuthService->getEnabledProviders(),
        ]);
    }

    /**
     * Get redirect URL for a provider (for SPA/mobile)
     */
    public function getRedirectUrl(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $url = $this->socialAuthService->getRedirectUrl($provider, [
            'stateless' => true,
            'tenant_id' => $tenantId,
        ]);

        return response()->json(['url' => $url]);
    }

    /**
     * Handle social login with token from frontend
     */
    public function login(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $tenantId = $request->attributes->get('tenant_id');

        try {
            // Get user info from provider using access token
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $customer = $this->socialAuthService->findOrCreateCustomer(
                $socialUser,
                $provider,
                $tenantId
            );

            // Create API token
            $token = $customer->createToken('social-auth')->plainTextToken;

            return response()->json([
                'customer' => $customer,
                'token' => $token,
                'is_new' => $customer->wasRecentlyCreated,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Link social account to authenticated customer
     */
    public function link(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $customer = $request->user('customer');

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->access_token);

            $this->socialAuthService->linkSocialAccount($customer, $socialUser, $provider);

            return response()->json([
                'message' => 'Account linked successfully',
                'linked_providers' => $customer->getLinkedProviders(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Unlink social account
     */
    public function unlink(Request $request, string $provider): JsonResponse
    {
        $customer = $request->user('customer');

        try {
            $this->socialAuthService->unlinkSocialAccount($customer, $provider);

            return response()->json([
                'message' => 'Account unlinked successfully',
                'linked_providers' => $customer->fresh()->getLinkedProviders(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get linked accounts
     */
    public function linkedAccounts(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        $accounts = $customer->socialAccounts->map(fn($account) => [
            'provider' => $account->provider,
            'email' => $account->email,
            'name' => $account->name,
            'avatar' => $account->avatar,
            'linked_at' => $account->created_at,
        ]);

        return response()->json([
            'accounts' => $accounts,
            'can_unlink' => $customer->canUnlinkSocialAccount(),
        ]);
    }
}
```

### 7. Routes

Add to `routes/web.php`:

```php
// Social Authentication (Web)
Route::prefix('auth')->group(function () {
    Route::get('/social/providers', [SocialAuthController::class, 'providers']);
    Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/{provider}/callback', [SocialAuthController::class, 'callback']);

    // Authenticated routes for linking
    Route::middleware('auth')->group(function () {
        Route::get('/{provider}/link', [SocialAuthController::class, 'link']);
        Route::get('/{provider}/link/callback', [SocialAuthController::class, 'linkCallback']);
        Route::delete('/{provider}/unlink', [SocialAuthController::class, 'unlink']);
    });
});
```

Add to `routes/api.php`:

```php
// Tenant Client Social Auth
Route::prefix('tenant-client')->middleware(['tenant'])->group(function () {
    Route::get('/auth/social/providers', [TenantClient\SocialAuthController::class, 'providers']);
    Route::get('/auth/social/{provider}/redirect', [TenantClient\SocialAuthController::class, 'getRedirectUrl']);
    Route::post('/auth/social/{provider}/login', [TenantClient\SocialAuthController::class, 'login']);

    // Authenticated
    Route::middleware('auth:customer')->group(function () {
        Route::get('/auth/social/accounts', [TenantClient\SocialAuthController::class, 'linkedAccounts']);
        Route::post('/auth/social/{provider}/link', [TenantClient\SocialAuthController::class, 'link']);
        Route::delete('/auth/social/{provider}/unlink', [TenantClient\SocialAuthController::class, 'unlink']);
    });
});
```

---

## Frontend Integration

### Login Button Component (Blade example)

```blade
<div class="social-login-buttons">
    @foreach($providers as $key => $provider)
        <a href="{{ route('social.redirect', $key) }}"
           class="btn btn-social btn-{{ $key }}"
           style="background-color: {{ $provider['color'] }}">
            <i class="icon-{{ $provider['icon'] }}"></i>
            Continue with {{ $provider['name'] }}
        </a>
    @endforeach
</div>
```

### JavaScript (for SPA)

```javascript
async function socialLogin(provider) {
    // Get redirect URL
    const response = await fetch(`/api/tenant-client/auth/social/${provider}/redirect`);
    const { url } = await response.json();

    // Open popup
    const popup = window.open(url, 'social-login', 'width=500,height=600');

    // Listen for callback
    window.addEventListener('message', async (event) => {
        if (event.data.type === 'social-auth-callback') {
            const { access_token } = event.data;

            // Exchange token for session
            const loginResponse = await fetch(`/api/tenant-client/auth/social/${provider}/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ access_token }),
            });

            const { token, customer } = await loginResponse.json();
            // Store token and redirect
        }
    });
}
```

---

## Testing Checklist

1. [ ] Google OAuth redirect works
2. [ ] Google callback creates new customer
3. [ ] Google callback logs in existing customer
4. [ ] Facebook OAuth works similarly
5. [ ] Apple Sign In works
6. [ ] Auto-link by email works
7. [ ] Link additional provider to existing account
8. [ ] Unlink provider (with password set)
9. [ ] Cannot unlink last provider without password
10. [ ] Profile data (name, avatar) is populated
11. [ ] Email is marked as verified for social logins
12. [ ] API token-based flow works for SPA
13. [ ] Tenant context is preserved through OAuth flow
