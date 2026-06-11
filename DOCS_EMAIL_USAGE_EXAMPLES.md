# Email Templates - Usage Examples

## Running the Seeder

First, populate the database with example email templates:

```bash
php artisan db:seed --class=EmailTemplatesSeeder
```

This will create 3 example templates:
1. **Registration Confirmation** - Email verification after signup
2. **Welcome Email** - After email verification
3. **Password Reset** - Password reset request

---

## How to Send Emails Using Templates

### 1. Registration Confirmation Email (Onboarding)

Add this to `OnboardingController@storeStepFour` after creating the tenant:

```php
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// After creating tenant and user in storeStepFour()...

// Generate verification token
$verificationToken = Str::random(64);

// TODO: Store token in database (create email_verifications table)
// DB::table('email_verifications')->insert([
//     'email' => $user->email,
//     'token' => hash('sha256', $verificationToken),
//     'created_at' => now(),
// ]);

// Get the email template
$template = EmailTemplate::where('event_trigger', 'registration_confirmation')
    ->where('is_active', true)
    ->first();

if ($template) {
    // Prepare variables
    $variables = [
        'first_name' => $step1['first_name'],
        'last_name' => $step1['last_name'],
        'full_name' => $step1['first_name'] . ' ' . $step1['last_name'],
        'email' => $step1['email'],
        'company_name' => $step2['company_name'],
        'public_name' => $step1['public_name'],
        'verification_link' => route('onboarding.verify', ['token' => $verificationToken]),
    ];

    // Process template
    $processedEmail = $template->processTemplate($variables);

    // Send email
    Mail::send([], [], function ($message) use ($processedEmail, $step1) {
        $message->to($step1['email'], $step1['first_name'] . ' ' . $step1['last_name'])
                ->subject($processedEmail['subject'])
                ->html($processedEmail['body']);
    });

    // Log the email
    EmailLog::create([
        'email_template_id' => $template->id,
        'recipient_email' => $step1['email'],
        'recipient_name' => $step1['first_name'] . ' ' . $step1['last_name'],
        'subject' => $processedEmail['subject'],
        'body' => $processedEmail['body'],
        'status' => 'sent',
        'sent_at' => now(),
        'metadata' => $variables,
        'tenant_id' => $tenant->id,
    ]);
}
```

---

### 2. Welcome Email (After Email Verification)

Add this to `OnboardingController@verify` after successful verification:

```php
// After verifying email...

$tenant = Tenant::where('contact_email', $email)->first();
$user = User::where('email', $email)->first();

if ($tenant && $user) {
    // Get template
    $template = EmailTemplate::where('event_trigger', 'welcome_email')
        ->where('is_active', true)
        ->first();

    if ($template) {
        $variables = [
            'first_name' => $user->name,
            'last_name' => '',
            'full_name' => $user->name,
            'email' => $user->email,
            'company_name' => $tenant->company_name,
            'public_name' => $tenant->public_name,
            'plan' => ucfirst(str_replace('percent', '%', $tenant->plan)),
            'website_url' => 'https://' . $tenant->domain,
        ];

        $processedEmail = $template->processTemplate($variables);

        Mail::send([], [], function ($message) use ($processedEmail, $user) {
            $message->to($user->email, $user->name)
                    ->subject($processedEmail['subject'])
                    ->html($processedEmail['body']);
        });

        EmailLog::create([
            'email_template_id' => $template->id,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $processedEmail['subject'],
            'body' => $processedEmail['body'],
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => $variables,
            'tenant_id' => $tenant->id,
        ]);
    }
}
```

---

### 3. Password Reset Email

Create a `PasswordResetController` or add to existing auth controller:

```php
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

public function sendResetLink(Request $request)
{
    $request->validate(['email' => 'required|email']);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return back()->with('error', 'Email not found');
    }

    // Generate reset token
    $resetToken = Str::random(64);

    // TODO: Store token in password_resets table
    // DB::table('password_resets')->updateOrInsert(
    //     ['email' => $user->email],
    //     [
    //         'email' => $user->email,
    //         'token' => Hash::make($resetToken),
    //         'created_at' => now(),
    //     ]
    // );

    // Get template
    $template = EmailTemplate::where('event_trigger', 'password_reset')
        ->where('is_active', true)
        ->first();

    if ($template) {
        $variables = [
            'first_name' => explode(' ', $user->name)[0],
            'last_name' => explode(' ', $user->name)[1] ?? '',
            'full_name' => $user->name,
            'email' => $user->email,
            'reset_password_link' => route('password.reset', ['token' => $resetToken, 'email' => $user->email]),
        ];

        $processedEmail = $template->processTemplate($variables);

        Mail::send([], [], function ($message) use ($processedEmail, $user) {
            $message->to($user->email, $user->name)
                    ->subject($processedEmail['subject'])
                    ->html($processedEmail['body']);
        });

        EmailLog::create([
            'email_template_id' => $template->id,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $processedEmail['subject'],
            'body' => $processedEmail['body'],
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => $variables,
        ]);
    }

    return back()->with('success', 'Password reset link sent to your email');
}
```

---

## Error Handling

Wrap email sending in try-catch:

```php
try {
    Mail::send([], [], function ($message) use ($processedEmail, $user) {
        $message->to($user->email, $user->name)
                ->subject($processedEmail['subject'])
                ->html($processedEmail['body']);
    });

    $emailLog->markAsSent();
} catch (\Exception $e) {
    \Log::error('Email sending failed', [
        'email' => $user->email,
        'error' => $e->getMessage(),
    ]);

    $emailLog->markAsFailed($e->getMessage());
}
```

---

## Available Variables by Event Type

### Registration Confirmation
- `first_name`, `last_name`, `full_name`
- `email`
- `company_name`, `public_name`
- `verification_link`

### Welcome Email
- `first_name`, `last_name`, `full_name`
- `email`
- `company_name`, `public_name`
- `plan`
- `website_url`

### Password Reset
- `first_name`, `last_name`, `full_name`
- `email`
- `reset_password_link`

---

## Creating Custom Templates

1. Go to **Admin Panel** → **Communications** → **Email Templates**
2. Click **Create New**
3. Fill in:
   - **Template Name**: Human-readable name
   - **Event Trigger**: Select from dropdown
   - **Description**: Internal notes
   - **Subject**: Use `{{variable_name}}` syntax
   - **Body**: HTML content with variables
   - **Available Variables**: List of variables for this template
4. Click variables in the helper box to copy them
5. Save and activate

---

## Testing Templates

You can test template rendering:

```php
$template = EmailTemplate::find(1);

$testVariables = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    // ... other variables
];

$processed = $template->processTemplate($testVariables);

dd($processed);
// ['subject' => 'Welcome John!', 'body' => '<h2>Welcome John!</h2>...']
```
