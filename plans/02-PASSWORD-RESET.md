# Password Reset Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Currently, users who forget their passwords have no way to recover their accounts. This creates:
1. **Lost customers**: Users who can't log in simply abandon the platform
2. **Support burden**: Manual password resets require admin intervention
3. **Security risks**: No automated, secure way to reset compromised passwords
4. **Poor UX**: Industry-standard feature is missing

### What This Feature Does
Implements a complete password reset flow that:
- Allows users to request password reset via email
- Sends secure, time-limited reset tokens
- Validates tokens before allowing password change
- Handles both admin Users and tenant Customers
- Prevents token reuse and brute force attacks
- Logs password reset events for security auditing

---

## Technical Implementation

### 1. Database Migrations

Create `database/migrations/2026_01_03_000002_create_password_resets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For admin users (if not already exists from Laravel default)
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // For tenant customers - separate table with tenant context
        Schema::create('customer_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->nullable();

            $table->unique(['email', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_resets');
    }
};
```

### 2. Configuration

Add to `config/auth.php`:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60, // Token expires in 60 minutes
        'throttle' => 60, // Wait 60 seconds before resending
    ],
    'customers' => [
        'provider' => 'customers',
        'table' => 'customer_password_resets',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

### 3. Service Class

Create `app/Services/Auth/PasswordResetService.php`:

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Customer;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class PasswordResetService
{
    /**
     * Send password reset link to user
     */
    public function sendResetLink(string $email, ?int $tenantId = null): array
    {
        // Determine if this is a customer or admin user request
        $isCustomer = $tenantId !== null;

        // Rate limiting
        $key = 'password-reset:' . ($isCustomer ? "customer:{$tenantId}:" : 'user:') . $email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return [
                'success' => false,
                'message' => "Too many reset attempts. Please try again in {$seconds} seconds.",
            ];
        }

        // Find the user
        if ($isCustomer) {
            $user = Customer::where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();
            $table = 'customer_password_resets';
        } else {
            $user = User::where('email', $email)->first();
            $table = 'password_reset_tokens';
        }

        // Always return success to prevent email enumeration
        if (!$user) {
            RateLimiter::hit($key, 300);
            return [
                'success' => true,
                'message' => 'If an account exists with this email, you will receive a password reset link.',
            ];
        }

        // Generate token
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        // Store token
        if ($isCustomer) {
            DB::table($table)->updateOrInsert(
                ['email' => $email, 'tenant_id' => $tenantId],
                ['token' => $hashedToken, 'created_at' => now()]
            );
        } else {
            DB::table($table)->updateOrInsert(
                ['email' => $email],
                ['token' => $hashedToken, 'created_at' => now()]
            );
        }

        // Send email
        Mail::to($email)->queue(new ResetPasswordMail($user, $token, $isCustomer, $tenantId));

        RateLimiter::hit($key, 300);

        return [
            'success' => true,
            'message' => 'If an account exists with this email, you will receive a password reset link.',
        ];
    }

    /**
     * Validate reset token
     */
    public function validateToken(string $email, string $token, ?int $tenantId = null): bool
    {
        $isCustomer = $tenantId !== null;
        $table = $isCustomer ? 'customer_password_resets' : 'password_reset_tokens';

        $query = DB::table($table)->where('email', $email);

        if ($isCustomer) {
            $query->where('tenant_id', $tenantId);
        }

        $record = $query->first();

        if (!$record) {
            return false;
        }

        // Check if token is expired
        $expireMinutes = config('auth.passwords.' . ($isCustomer ? 'customers' : 'users') . '.expire', 60);
        $createdAt = Carbon::parse($record->created_at);

        if ($createdAt->addMinutes($expireMinutes)->isPast()) {
            return false;
        }

        // Verify token hash
        return Hash::check($token, $record->token);
    }

    /**
     * Reset password using token
     */
    public function reset(string $email, string $token, string $password, ?int $tenantId = null): array
    {
        // Validate token first
        if (!$this->validateToken($email, $token, $tenantId)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ];
        }

        $isCustomer = $tenantId !== null;

        // Find user and update password
        if ($isCustomer) {
            $user = Customer::where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();
            $table = 'customer_password_resets';
        } else {
            $user = User::where('email', $email)->first();
            $table = 'password_reset_tokens';
        }

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        // Update password
        $user->password = Hash::make($password);
        $user->save();

        // Delete used token
        $query = DB::table($table)->where('email', $email);
        if ($isCustomer) {
            $query->where('tenant_id', $tenantId);
        }
        $query->delete();

        // Log the password reset for security
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Password reset completed');

        return [
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ];
    }

    /**
     * Delete expired tokens (for cleanup command)
     */
    public function deleteExpiredTokens(): int
    {
        $deleted = 0;

        // Clean user tokens
        $expireMinutes = config('auth.passwords.users.expire', 60);
        $deleted += DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subMinutes($expireMinutes))
            ->delete();

        // Clean customer tokens
        $expireMinutes = config('auth.passwords.customers.expire', 60);
        $deleted += DB::table('customer_password_resets')
            ->where('created_at', '<', now()->subMinutes($expireMinutes))
            ->delete();

        return $deleted;
    }
}
```

### 4. Mail Class

Create `app/Mail/ResetPasswordMail.php`:

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

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User|Customer $user,
        public string $token,
        public bool $isCustomer = false,
        public ?int $tenantId = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password',
        );
    }

    public function content(): Content
    {
        $resetUrl = $this->generateResetUrl();

        return new Content(
            view: 'emails.reset-password',
            with: [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
                'expiresIn' => config('auth.passwords.' . ($this->isCustomer ? 'customers' : 'users') . '.expire', 60),
            ],
        );
    }

    protected function generateResetUrl(): string
    {
        $params = [
            'token' => $this->token,
            'email' => urlencode($this->user->email),
        ];

        if ($this->isCustomer && $this->tenantId) {
            $params['tenant'] = $this->tenantId;
            return url('/reset-password?' . http_build_query($params));
        }

        return url('/admin/reset-password?' . http_build_query($params));
    }
}
```

### 5. Email Template

Create `resources/views/emails/reset-password.blade.php`:

```blade
@component('mail::message')
# Reset Your Password

Hello {{ $user->name ?? $user->first_name ?? 'there' }},

You are receiving this email because we received a password reset request for your account.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

This password reset link will expire in {{ $expiresIn }} minutes.

If you did not request a password reset, no further action is required. Your password will remain unchanged.

**Security Notice:** If you did not request this password reset and believe someone else may have access to your account, please contact our support team immediately.

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser: [{{ $resetUrl }}]({{ $resetUrl }})
@endcomponent
@endcomponent
```

### 6. Request Validation Classes

Create `app/Http/Requests/Auth/ForgotPasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
```

Create `app/Http/Requests/Auth/ResetPasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Reset token is required.',
            'token.size' => 'Invalid reset token.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
```

### 7. Controllers

Create `app/Http/Controllers/Auth/PasswordResetController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService
    ) {}

    /**
     * Send password reset link (admin users)
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->sendResetLink($request->email);

        return response()->json([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 429);
    }

    /**
     * Validate reset token
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:64',
        ]);

        $isValid = $this->passwordResetService->validateToken(
            $request->email,
            $request->token
        );

        return response()->json([
            'valid' => $isValid,
        ]);
    }

    /**
     * Reset password
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->reset(
            $request->email,
            $request->token,
            $request->password
        );

        return response()->json([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Show reset password form (web)
     */
    public function showResetForm(Request $request)
    {
        return view('auth.reset-password', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }
}
```

Create `app/Http/Controllers/Api/TenantClient/PasswordResetController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService
    ) {}

    /**
     * Send password reset link (customers)
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        // Get tenant ID from request context (set by TenantMiddleware)
        $tenantId = $request->attributes->get('tenant_id')
            ?? $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant context required.',
            ], 400);
        }

        $result = $this->passwordResetService->sendResetLink(
            $request->email,
            (int) $tenantId
        );

        return response()->json([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 429);
    }

    /**
     * Validate reset token
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:64',
        ]);

        $tenantId = $request->attributes->get('tenant_id')
            ?? $request->header('X-Tenant-ID');

        $isValid = $this->passwordResetService->validateToken(
            $request->email,
            $request->token,
            (int) $tenantId
        );

        return response()->json([
            'valid' => $isValid,
        ]);
    }

    /**
     * Reset password
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? $request->header('X-Tenant-ID')
            ?? $request->input('tenant_id');

        $result = $this->passwordResetService->reset(
            $request->email,
            $request->token,
            $request->password,
            (int) $tenantId
        );

        return response()->json([
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }
}
```

### 8. Routes

Add to `routes/api.php`:

```php
// Admin user password reset (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/validate-reset-token', [PasswordResetController::class, 'validateToken']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

// Tenant client customer password reset
Route::prefix('tenant-client')->middleware(['tenant'])->group(function () {
    Route::post('/auth/forgot-password', [TenantClient\PasswordResetController::class, 'sendResetLink']);
    Route::post('/auth/validate-reset-token', [TenantClient\PasswordResetController::class, 'validateToken']);
    Route::post('/auth/reset-password', [TenantClient\PasswordResetController::class, 'reset']);
});
```

Add to `routes/web.php`:

```php
Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::get('/admin/reset-password', [PasswordResetController::class, 'showResetForm'])
    ->name('admin.password.reset');
```

### 9. Cleanup Command

Create `app/Console/Commands/CleanupPasswordResets.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Auth\PasswordResetService;
use Illuminate\Console\Command;

class CleanupPasswordResets extends Command
{
    protected $signature = 'auth:cleanup-password-resets';
    protected $description = 'Delete expired password reset tokens';

    public function handle(PasswordResetService $service): int
    {
        $deleted = $service->deleteExpiredTokens();

        $this->info("Deleted {$deleted} expired password reset token(s).");

        return Command::SUCCESS;
    }
}
```

Add to scheduler in `routes/console.php` or `app/Console/Kernel.php`:

```php
Schedule::command('auth:cleanup-password-resets')->daily();
```

### 10. Reset Password View (Web)

Create `resources/views/auth/reset-password.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Reset your password
            </h2>
        </div>

        <form class="mt-8 space-y-6" method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <label for="password" class="sr-only">New Password</label>
                <input id="password" name="password" type="password" required
                    class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                    placeholder="New password">
            </div>

            <div>
                <label for="password_confirmation" class="sr-only">Confirm Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                    placeholder="Confirm new password">
            </div>

            <div>
                <button type="submit"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Reset Password
                </button>
            </div>
        </form>

        @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4">
            <ul class="list-disc list-inside text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
```

---

## Security Considerations

1. **Token Hashing**: Tokens are stored as hashes, not plain text
2. **Rate Limiting**: Prevents brute force and email enumeration
3. **Generic Messages**: Always returns same message to prevent email enumeration
4. **Token Expiration**: Tokens expire after configurable time
5. **Single Use**: Tokens are deleted after successful reset
6. **Audit Logging**: Password resets are logged for security monitoring
7. **Strong Password Requirements**: Enforced via validation rules

---

## Testing Checklist

1. [ ] User can request password reset
2. [ ] Reset email is sent with valid link
3. [ ] Token validation works correctly
4. [ ] Expired tokens are rejected
5. [ ] Password is updated successfully
6. [ ] Token is deleted after use
7. [ ] Rate limiting prevents abuse
8. [ ] Works for both Users and Customers
9. [ ] Invalid tokens are rejected
10. [ ] Password validation rules are enforced
