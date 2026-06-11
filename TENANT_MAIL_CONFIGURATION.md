# Tenant Mail Configuration System

## Overview

Sistemul permite fiecărui tenant să folosească propriile credențiale de mail (Gmail, Outlook, SMTP custom, etc.) pentru trimiterea emailurilor. Dacă tenant-ul nu are configurație setată, se folosește automat configurația core (Brevo din .env).

### Key Features

✅ **Dynamic mail configuration per tenant** - fără modificări .env
✅ **Automatic fallback** la core mail (Brevo) dacă tenant nu are config
✅ **Scalabil** pentru 1000+ tenants
✅ **Securitate** - passwords encrypted în database
✅ **Test configuration** - endpoint pentru testare configurație mail

---

## How It Works

### 1. Arhitectura

```
┌─────────────────────────────────────────────────────────────┐
│                      Email Sending Flow                      │
└─────────────────────────────────────────────────────────────┘

AuthController (register/resend)
        │
        ├──> TenantMailService.send(tenant, callback)
        │
        └──> Check: Tenant are config propriu?
                │
                ├─ DA  ──> Use tenant config (SMTP tenant)
                │           └─> Eroare? ──> Fallback la core
                │
                └─ NU  ──> Use core config (Brevo .env)
```

### 2. Stocarea Configurației

Configurația de mail pentru fiecare tenant se stochează în coloana `settings` (JSON) din tabela `tenants`:

```json
{
  "mail": {
    "driver": "smtp",
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "tenant@example.com",
    "password": "eyJpdiI6ImlQU0pYNjJ...",  // Encrypted password
    "encryption": "tls",
    "from_address": "noreply@tenantdomain.com",
    "from_name": "Tenant Name"
  }
}
```

**Câmpuri suportate**:
- `driver` - smtp, mailgun, ses, postmark, log (default: smtp)
- `host` - SMTP server hostname
- `port` - SMTP port (default: 587)
- `username` - SMTP username/email
- `password` - SMTP password (**TREBUIE encrypted cu `encrypt()`**)
- `encryption` - tls, ssl, null (default: tls)
- `from_address` - Email address for sending
- `from_name` - Display name for sender

---

## Usage

### 1. Configurare Tenant prin Filament (Manual)

În Filament Admin → Tenants → Edit Tenant → Settings Tab:

```php
// Option 1: Add to existing TenantResource form
Forms\Components\Section::make('Mail Configuration')
    ->schema([
        Forms\Components\Select::make('settings.mail.driver')
            ->label('Mail Driver')
            ->options([
                'smtp' => 'SMTP',
                'mailgun' => 'Mailgun',
                'ses' => 'Amazon SES',
                'postmark' => 'Postmark',
            ])
            ->default('smtp'),

        Forms\Components\TextInput::make('settings.mail.host')
            ->label('SMTP Host')
            ->placeholder('smtp.gmail.com'),

        Forms\Components\TextInput::make('settings.mail.port')
            ->label('SMTP Port')
            ->numeric()
            ->default(587),

        Forms\Components\TextInput::make('settings.mail.username')
            ->label('SMTP Username/Email')
            ->email(),

        Forms\Components\TextInput::make('settings.mail.password')
            ->label('SMTP Password')
            ->password()
            ->dehydrateStateUsing(fn ($state) => encrypt($state))
            ->dehydrated(fn ($state) => filled($state)),

        Forms\Components\Select::make('settings.mail.encryption')
            ->label('Encryption')
            ->options([
                'tls' => 'TLS',
                'ssl' => 'SSL',
                null => 'None',
            ])
            ->default('tls'),

        Forms\Components\TextInput::make('settings.mail.from_address')
            ->label('From Email')
            ->email(),

        Forms\Components\TextInput::make('settings.mail.from_name')
            ->label('From Name'),
    ])
    ->collapsible()
    ->collapsed(),
```

### 2. Configurare Programatică

```php
use App\Models\Tenant;

$tenant = Tenant::find(1);

$tenant->settings = array_merge($tenant->settings ?? [], [
    'mail' => [
        'driver' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'tenant@gmail.com',
        'password' => encrypt('tenant-password'),  // IMPORTANT: encrypt()
        'encryption' => 'tls',
        'from_address' => 'noreply@tenantdomain.com',
        'from_name' => 'My Tenant Name',
    ],
]);

$tenant->save();
```

### 3. Test Mail Configuration

```php
use App\Services\TenantMailService;
use App\Models\Tenant;

$tenant = Tenant::find(1);
$mailService = app(TenantMailService::class);

$result = $mailService->testTenantMailConfig($tenant, 'test@example.com');

if ($result['success']) {
    echo "✅ Email sent successfully!";
    echo "Using config: " . $result['details']['using_config']; // "tenant" or "core"
} else {
    echo "❌ Failed: " . $result['message'];
    dd($result['details']);
}
```

---

## Common Mail Providers

### Gmail

```json
{
  "mail": {
    "driver": "smtp",
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "your-email@gmail.com",
    "password": "encrypted_app_password",
    "encryption": "tls"
  }
}
```

**Important**: Gmail necesită **App Password** (nu parola contului):
1. Activează 2FA pe contul Google
2. Mergi la https://myaccount.google.com/apppasswords
3. Generează App Password pentru "Mail"
4. Folosește acest password (encrypt înainte de salvare)

### Microsoft 365 / Outlook.com

```json
{
  "mail": {
    "driver": "smtp",
    "host": "smtp.office365.com",
    "port": 587,
    "username": "your-email@outlook.com",
    "password": "encrypted_password",
    "encryption": "tls"
  }
}
```

### Custom SMTP (cPanel, Plesk, etc.)

```json
{
  "mail": {
    "driver": "smtp",
    "host": "mail.yourdomain.com",
    "port": 465,
    "username": "noreply@yourdomain.com",
    "password": "encrypted_password",
    "encryption": "ssl"
  }
}
```

### Mailgun

```json
{
  "mail": {
    "driver": "mailgun",
    "host": "smtp.mailgun.org",
    "port": 587,
    "username": "postmaster@yourdomain.mailgun.org",
    "password": "encrypted_api_key",
    "encryption": "tls"
  }
}
```

---

## Core Mail Configuration (Fallback - Brevo)

În `.env` pe server:

```env
# Brevo SMTP Configuration (folosit ca fallback)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your-brevo-email@example.com
MAIL_PASSWORD=your-brevo-smtp-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@core.tixello.com
MAIL_FROM_NAME="Tixello Core"
```

---

## Security Best Practices

### 1. **ÎNTOTDEAUNA encrypt passwords**:

```php
// ❌ WRONG
$tenant->settings['mail']['password'] = 'plain-password';

// ✅ CORRECT
$tenant->settings['mail']['password'] = encrypt('plain-password');
```

### 2. **Validare domenii**:

Opțional, adaugă validare că tenant-ul poate trimite doar de pe propriul domeniu:

```php
// În TenantMailService.php
private function validateFromAddress(Tenant $tenant, string $fromAddress): bool
{
    $tenantDomains = $tenant->domains->pluck('domain')->toArray();
    $emailDomain = substr(strrchr($fromAddress, "@"), 1);

    return in_array($emailDomain, $tenantDomains);
}
```

### 3. **Rate Limiting**:

Adaugă rate limiting per tenant pentru anti-spam:

```php
// În routes/api.php
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
});
```

---

## Debugging & Logs

Toate emailurile trimise (sau eșuate) sunt logate în `storage/logs/laravel.log`:

### Succes cu tenant config:
```
[2025-11-27 10:15:32] local.INFO: Email sent using tenant mail configuration
{"tenant_id": 1, "mail_host": "smtp.gmail.com"}
```

### Succes cu core config (fallback):
```
[2025-11-27 10:15:32] local.INFO: Email sent using core mail configuration (Brevo)
```

### Eroare:
```
[2025-11-27 10:15:32] local.ERROR: Failed to send email with tenant configuration, falling back to core
{"tenant_id": 1, "error": "Connection timeout"}
```

---

## API pentru Test Mail (Opțional)

Poți crea un endpoint pentru ca tenant-ul să testeze configurația:

```php
// routes/api.php
Route::post('/tenant/test-mail', function (Request $request) {
    $tenant = auth()->user()->tenant; // sau rezolvă tenant-ul altfel

    $validated = $request->validate([
        'test_email' => 'required|email',
    ]);

    $mailService = app(\App\Services\TenantMailService::class);
    $result = $mailService->testTenantMailConfig($tenant, $validated['test_email']);

    return response()->json($result);
});
```

---

## Migration Example (Opțional)

Dacă vrei să adaugi coloane dedicate în loc de JSON `settings`:

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->string('mail_driver')->nullable();
    $table->string('mail_host')->nullable();
    $table->integer('mail_port')->nullable();
    $table->string('mail_username')->nullable();
    $table->text('mail_password')->nullable(); // Encrypted
    $table->string('mail_encryption')->nullable();
    $table->string('mail_from_address')->nullable();
    $table->string('mail_from_name')->nullable();
});
```

Apoi actualizează `TenantMailService` să citească din coloane în loc de JSON.

---

## Întrebări Frecvente

### 1. Ce se întâmplă dacă tenant-ul nu setează configurație de mail?

Se folosește automat configurația core (Brevo din .env). Nu e nevoie să faci nimic special.

### 2. Pot folosi Mailgun/SES/Postmark pentru tenant?

Da! Setează `driver` în configurație:
- `smtp` - Generic SMTP
- `mailgun` - Mailgun API
- `ses` - Amazon SES
- `postmark` - Postmark API

Pentru API-based drivers (mailgun, ses, postmark), trebuie să instalezi și package-urile corespunzătoare din Composer.

### 3. Cum șt

erg configurația greșită?

TenantMailService are fallback automat: dacă trimitereastă cu config tenant eșuează, încearcă automat cu config core (Brevo).

### 4. Passwords sunt sigure?

Da, dacă folosești `encrypt()` când salvezi. TenantMailService decryptează automat când trimite emailul.

---

## Support

Pentru probleme:
1. Verifică `storage/logs/laravel.log`
2. Testează configurația cu `testTenantMailConfig()`
3. Verifică că password-ul este encrypted în DB

---

**Generated**: 2025-11-27
**Version**: 1.0
