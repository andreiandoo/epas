# Email Verification Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently, users can register and create accounts without verifying their email addresses. This creates several problems:
1. **Fake accounts**: Anyone can create accounts with non-existent or others' email addresses
2. **Undeliverable communications**: Order confirmations, tickets, and reminders may never reach customers
3. **Security risks**: Account recovery becomes unreliable without verified emails
4. **Data quality**: Customer database contains unverified, potentially invalid contact information
5. **Spam prevention**: Bots can create unlimited accounts

### What This Feature Does
Implements a complete email verification system that:
- Sends verification emails upon registration
- Validates email ownership via unique tokens
- Blocks certain actions until email is verified
- Allows resending verification emails
- Handles token expiration gracefully
- Works for both admin Users and tenant Customers

---

## Technical Implementation

### 1. Database Migrations

Create migration file `database/migrations/2026_01_03_000001_add_email_verification_fields.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add to users table (admin users)
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_token', 64)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_token');
        });

        // Add to customers table (tenant customers)
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_token', 64)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'email_verification_token', 'email_verification_sent_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'email_verification_token', 'email_verification_sent_at']);
        });
    }
};
```

### 2. Configuration

Add to `config/auth.php`:

```php
'verification' => [
    'expire' => env('EMAIL_VERIFICATION_EXPIRE_MINUTES', 60), // Token expires in 60 minutes
    'throttle' => env('EMAIL_VERIFICATION_THROTTLE', 6), // Max 6 resend attempts per hour
],
```

Add to `.env.example`:

```
EMAIL_VERIFICATION_EXPIRE_MINUTES=60
EMAIL_VERIFICATION_THROTTLE=6
EMAIL_VERIFICATION_REQUIRED=true
```

### 3. Service Class

Create `app/Services/Auth/EmailVerificationService.php`:

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Customer;
use App\Mail\VerifyEmailMail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class EmailVerificationService
{
    /**
     * Generate a verification token and send verification email
     */
    public function sendVerificationEmail(User|Customer $user): bool
    {
        // Check rate limiting
        $key = 'verify-email:' . $user->id . ':' . get_class($user);

        if (RateLimiter::tooManyAttempts($key, config('auth.verification.throttle', 6))) {
            $seconds = RateLimiter::availableIn($key);
            throw new \Exception("Too many verification attempts. Please try again in {$seconds} seconds.");
        }

        // Generate token
        $token = Str::random(64);

        // Save token to user
        $user->email_verification_token = $token;
        $user->email_verification_sent_at = now();
        $user->save();

        // Send email
        Mail::to($user->email)->queue(new VerifyEmailMail($user, $token));

        // Increment rate limiter
        RateLimiter::hit($key, 3600); // 1 hour window

        return true;
    }

    /**
     * Verify email using token
     */
    public function verify(string $token, string $userType = 'customer'): User|Customer|null
    {
        $model = $userType === 'user' ? User::class : Customer::class;

        $user = $model::where('email_verification_token', $token)->first();

        if (!$user) {
            return null;
        }

        // Check if token is expired
        if ($this->isTokenExpired($user)) {
            return null;
        }

        // Mark as verified
        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return $user;
    }

    /**
     * Check if verification token is expired
     */
    public function isTokenExpired(User|Customer $user): bool
    {
        if (!$user->email_verification_sent_at) {
            return true;
        }

        $expireMinutes = config('auth.verification.expire', 60);
        return $user->email_verification_sent_at->addMinutes($expireMinutes)->isPast();
    }

    /**
     * Check if user's email is verified
     */
    public function isVerified(User|Customer $user): bool
    {
        return $user->email_verified_at !== null;
    }

    /**
     * Resend verification email
     */
    public function resendVerification(User|Customer $user): bool
    {
        if ($this->isVerified($user)) {
            throw new \Exception('Email is already verified.');
        }

        return $this->sendVerificationEmail($user);
    }

    /**
     * Revoke verification token
     */
    public function revokeToken(User|Customer $user): void
    {
        $user->email_verification_token = null;
        $user->email_verification_sent_at = null;
        $user->save();
    }
}
```

### 4. Mail Class

Create `app/Mail/VerifyEmailMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User|Customer $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address',
        );
    }

    public function content(): Content
    {
        $userType = $this->user instanceof User ? 'user' : 'customer';
        $verificationUrl = $this->generateVerificationUrl($userType);

        return new Content(
            view: 'emails.verify-email',
            with: [
                'user' => $this->user,
                'verificationUrl' => $verificationUrl,
                'expiresIn' => config('auth.verification.expire', 60),
            ],
        );
    }

    protected function generateVerificationUrl(string $userType): string
    {
        if ($userType === 'customer') {
            // For customers, include tenant context
            return url("/verify-email/{$this->token}?type=customer");
        }

        return url("/admin/verify-email/{$this->token}?type=user");
    }
}
```

### 5. Email Template

Create `resources/views/emails/verify-email.blade.php`:

```blade
@component('mail::message')
# Verify Your Email Address

Hello {{ $user->name ?? $user->first_name ?? 'there' }},

Please click the button below to verify your email address.

@component('mail::button', ['url' => $verificationUrl])
Verify Email Address
@endcomponent

This verification link will expire in {{ $expiresIn }} minutes.

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser: [{{ $verificationUrl }}]({{ $verificationUrl }})
@endcomponent
@endcomponent
```

### 6. Controllers

Create `app/Http/Controllers/Auth/EmailVerificationController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $verificationService
    ) {}

    /**
     * Send verification email (web route)
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->verificationService->isVerified($user)) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        try {
            $this->verificationService->sendVerificationEmail($user);
            return response()->json(['message' => 'Verification email sent.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
    }

    /**
     * Verify email via token (web route)
     */
    public function verify(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $type = $request->query('type', 'customer');

        $user = $this->verificationService->verify($token, $type);

        if (!$user) {
            return redirect('/verification-failed')
                ->with('error', 'Invalid or expired verification link.');
        }

        $redirectUrl = $type === 'user' ? '/admin' : '/';

        return redirect($redirectUrl)
            ->with('success', 'Email verified successfully!');
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $this->verificationService->resendVerification($user);
            return response()->json(['message' => 'Verification email resent.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
```

Create `app/Http/Controllers/Api/TenantClient/EmailVerificationController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $verificationService
    ) {}

    /**
     * Send verification email to customer
     */
    public function send(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        if (!$customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($this->verificationService->isVerified($customer)) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        try {
            $this->verificationService->sendVerificationEmail($customer);
            return response()->json(['message' => 'Verification email sent.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
    }

    /**
     * Verify email via token (API)
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $customer = $this->verificationService->verify($request->token, 'customer');

        if (!$customer) {
            return response()->json([
                'message' => 'Invalid or expired verification token.'
            ], 400);
        }

        return response()->json([
            'message' => 'Email verified successfully.',
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->email,
                'email_verified_at' => $customer->email_verified_at,
            ]
        ]);
    }

    /**
     * Check verification status
     */
    public function status(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        return response()->json([
            'is_verified' => $this->verificationService->isVerified($customer),
            'email' => $customer->email,
            'verified_at' => $customer->email_verified_at,
        ]);
    }
}
```

### 7. Middleware

Create `app/Http/Middleware/EnsureEmailIsVerified.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->user('customer');

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!config('auth.verification.required', true)) {
            return $next($request);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php` or `bootstrap/app.php`:

```php
'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
```

### 8. Routes

Add to `routes/api.php`:

```php
// Public verification route
Route::post('/auth/verify-email', [EmailVerificationController::class, 'verify']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/verify-email/send', [EmailVerificationController::class, 'send']);
    Route::post('/auth/verify-email/resend', [EmailVerificationController::class, 'resend']);
});

// Tenant client routes
Route::prefix('tenant-client')->middleware(['tenant'])->group(function () {
    Route::post('/auth/verify-email', [TenantClient\EmailVerificationController::class, 'verify']);

    Route::middleware('auth:customer')->group(function () {
        Route::post('/auth/verify-email/send', [TenantClient\EmailVerificationController::class, 'send']);
        Route::get('/auth/verify-email/status', [TenantClient\EmailVerificationController::class, 'status']);
    });
});
```

Add to `routes/web.php`:

```php
Route::get('/verify-email/{token}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');
```

### 9. Modify Registration Flow

Update the user/customer registration to send verification email:

In your existing registration controller or service, add after creating the user:

```php
// After user creation
$verificationService = app(EmailVerificationService::class);
$verificationService->sendVerificationEmail($user);
```

### 10. Model Updates

Add to `app/Models/User.php`:

```php
protected $casts = [
    // ... existing casts
    'email_verified_at' => 'datetime',
    'email_verification_sent_at' => 'datetime',
];

public function hasVerifiedEmail(): bool
{
    return $this->email_verified_at !== null;
}

public function markEmailAsVerified(): bool
{
    return $this->forceFill([
        'email_verified_at' => now(),
        'email_verification_token' => null,
    ])->save();
}
```

Add to `app/Models/Customer.php`:

```php
protected $casts = [
    // ... existing casts
    'email_verified_at' => 'datetime',
    'email_verification_sent_at' => 'datetime',
];

public function hasVerifiedEmail(): bool
{
    return $this->email_verified_at !== null;
}
```

---

## Integration Points

### 1. OnboardingController
Find the `OnboardingController` and add verification email sending after tenant user creation.

### 2. Customer Registration
Find where customers are registered and trigger verification email.

### 3. Protected Routes
Apply the `verified` middleware to routes that require verified email:
- Order checkout
- Ticket purchases
- Profile updates

---

## Testing Checklist

1. [ ] User can register and receives verification email
2. [ ] Clicking verification link marks email as verified
3. [ ] Expired tokens are rejected
4. [ ] Rate limiting prevents spam
5. [ ] Resend functionality works
6. [ ] Protected routes block unverified users
7. [ ] Already verified users cannot re-verify
8. [ ] Works for both Users and Customers
