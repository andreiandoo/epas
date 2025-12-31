# 📱 Mobile Wallet Microservice

## Overview

Enable your customers to add event tickets to **Apple Wallet** and **Google Pay** for easy access on their mobile devices.

**Pricing:** €8/month per tenant

---

## Features

### Apple Wallet (iOS)
- Native .pkpass file generation
- Location-based notifications
- Time-relevant lock screen display
- Auto-updates when event changes
- Push notification support

### Google Pay (Android)
- JWT-based pass generation
- Material design passes
- Automatic updates via API
- Deep linking support

### Smart Features
- QR code barcode on passes
- Event details display
- Venue location with maps
- Ticket holder information
- Automatic voiding on cancellation

---

## Installation

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Register Service Provider

Add to `config/app.php` or service provider:

```php
// In MicroservicesServiceProvider::register()
$this->app->singleton(WalletService::class, function ($app) {
    $service = new WalletService();
    $service->registerAdapter('apple', new AppleWalletAdapter());
    $service->registerAdapter('google', new GoogleWalletAdapter());
    return $service;
});
```

### 3. Add Routes

```php
// In routes/api.php
Route::prefix('wallet')->group(function () {
    Route::post('/generate', [WalletController::class, 'generate']);
    Route::get('/download/{passId}/apple', [WalletController::class, 'downloadApple']);
    Route::get('/download/{passId}/google', [WalletController::class, 'downloadGoogle']);
    Route::get('/passes/{passId}', [WalletController::class, 'show']);
    Route::delete('/passes/{passId}', [WalletController::class, 'void']);
    Route::get('/stats', [WalletController::class, 'stats']);

    // Apple Wallet Web Service
    Route::post('/apple/devices/{deviceLibraryId}/registrations/{passTypeId}/{serialNumber}',
        [WalletController::class, 'registerDevice']);
    Route::delete('/apple/devices/{deviceLibraryId}/registrations/{passTypeId}/{serialNumber}',
        [WalletController::class, 'unregisterDevice']);
    Route::get('/apple/devices/{deviceLibraryId}/registrations/{passTypeId}',
        [WalletController::class, 'getSerialNumbers']);
    Route::get('/apple/passes/{passTypeId}/{serialNumber}',
        [WalletController::class, 'getLatestPass']);
    Route::post('/apple/log', [WalletController::class, 'log']);
});
```

### 4. Configure Environment

```env
# Apple Wallet
WALLET_APPLE_ENABLED=true
WALLET_APPLE_PASS_TYPE_IDENTIFIER=pass.com.yourcompany.events
WALLET_APPLE_TEAM_IDENTIFIER=ABCD1234
WALLET_APPLE_CERTIFICATE_PATH=/path/to/certificate.p12
WALLET_APPLE_CERTIFICATE_PASSWORD=your_password
WALLET_APPLE_WWDR_CERTIFICATE_PATH=/path/to/wwdr.pem

# Google Wallet
WALLET_GOOGLE_ENABLED=true
WALLET_GOOGLE_ISSUER_ID=your_issuer_id
WALLET_GOOGLE_SERVICE_ACCOUNT_EMAIL=service@project.iam.gserviceaccount.com
WALLET_GOOGLE_SERVICE_ACCOUNT_KEY=/path/to/service-account.json
```

---

## Configuration

Add to `config/microservices.php`:

```php
'wallet' => [
    'enabled' => env('WALLET_ENABLED', true),

    'apple' => [
        'enabled' => env('WALLET_APPLE_ENABLED', true),
        'pass_type_identifier' => env('WALLET_APPLE_PASS_TYPE_IDENTIFIER'),
        'team_identifier' => env('WALLET_APPLE_TEAM_IDENTIFIER'),
        'certificate_path' => env('WALLET_APPLE_CERTIFICATE_PATH'),
        'certificate_password' => env('WALLET_APPLE_CERTIFICATE_PASSWORD'),
        'wwdr_certificate_path' => env('WALLET_APPLE_WWDR_CERTIFICATE_PATH'),
    ],

    'google' => [
        'enabled' => env('WALLET_GOOGLE_ENABLED', true),
        'issuer_id' => env('WALLET_GOOGLE_ISSUER_ID'),
        'service_account_email' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_EMAIL'),
        'service_account_key' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_KEY'),
    ],
],
```

---

## API Endpoints

### Generate Pass

```http
POST /api/wallet/generate
Content-Type: application/json

{
    "ticket_id": 123,
    "platform": "apple", // or "google" or "both"
    "options": {
        "background_color": "#3c414c",
        "foreground_color": "#ffffff"
    }
}
```

**Response:**
```json
{
    "success": true,
    "pass_id": 456,
    "pass_url": "https://example.com/api/wallet/download/456/apple",
    "already_exists": false
}
```

### Download Apple Pass

```http
GET /api/wallet/download/{passId}/apple
```

Returns `.pkpass` file with `application/vnd.apple.pkpass` content type.

### Get Google Wallet URL

```http
GET /api/wallet/download/{passId}/google
```

**Response:**
```json
{
    "success": true,
    "save_url": "https://pay.google.com/gp/v/save/..."
}
```

### Get Pass Status

```http
GET /api/wallet/passes/{passId}
```

### Void Pass

```http
DELETE /api/wallet/passes/{passId}
```

### Get Statistics

```http
GET /api/wallet/stats
```

**Response:**
```json
{
    "success": true,
    "stats": {
        "total_passes": 1000,
        "active_passes": 950,
        "voided_passes": 50,
        "by_platform": {
            "apple": 600,
            "google": 350
        }
    }
}
```

---

## Usage Examples

### Generate pass after ticket purchase

```php
use App\Services\Wallet\WalletService;

class OrderController extends Controller
{
    public function complete(Order $order, WalletService $walletService)
    {
        // ... order completion logic ...

        // Generate wallet passes for all tickets
        foreach ($order->tickets as $ticket) {
            $walletService->generateAllPasses($ticket, [
                'background_color' => $order->tenant->primary_color,
            ]);
        }

        // Include pass URLs in confirmation email
        $applePassUrl = $walletService->getPassUrl($ticket->walletPasses()->apple()->first());
        $googlePassUrl = $walletService->getPassUrl($ticket->walletPasses()->google()->first());
    }
}
```

### Update passes when event changes

```php
use App\Services\Wallet\WalletService;

class EventController extends Controller
{
    public function update(Event $event, WalletService $walletService)
    {
        // ... update event logic ...

        // Update all wallet passes for this event
        $walletService->updatePassesForEvent($event->id, 'event_changed', [
            'new_date' => $event->start_date,
            'new_venue' => $event->venue->name,
        ]);
    }
}
```

### Void passes when ticket is cancelled

```php
use App\Services\Wallet\WalletService;

class TicketController extends Controller
{
    public function cancel(Ticket $ticket, WalletService $walletService)
    {
        // ... cancel ticket logic ...

        // Void all wallet passes
        $walletService->voidPassesForTicket($ticket->id);
    }
}
```

---

## Apple Wallet Setup

### 1. Create Pass Type ID

1. Go to [Apple Developer Portal](https://developer.apple.com/account)
2. Navigate to Certificates, IDs & Profiles
3. Create a new Pass Type ID
4. Download the certificate

### 2. Export Certificate

```bash
# Export from Keychain as .p12
# Then convert to PEM if needed
openssl pkcs12 -in certificate.p12 -out certificate.pem -nodes
```

### 3. Download WWDR Certificate

Download the Apple Worldwide Developer Relations Certificate from Apple.

---

## Google Wallet Setup

### 1. Enable Google Wallet API

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Enable the Google Wallet API
3. Create a service account
4. Download the service account JSON key

### 2. Register as Issuer

1. Go to [Google Pay & Wallet Console](https://pay.google.com/business/console)
2. Register your organization
3. Get your Issuer ID

---

## Database Schema

### wallet_passes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| tenant_id | bigint | Tenant reference |
| ticket_id | bigint | Ticket reference |
| order_id | bigint | Order reference |
| platform | enum | 'apple' or 'google' |
| pass_identifier | string | Unique pass identifier |
| serial_number | string | Platform-specific serial |
| auth_token | string | Authentication token |
| push_token | string | Push notification token |
| last_updated_at | timestamp | Last update time |
| voided_at | timestamp | When voided |

### wallet_push_registrations
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| pass_id | bigint | Pass reference |
| device_library_id | string | Apple device ID |
| push_token | string | APNs push token |

### wallet_pass_updates
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| pass_id | bigint | Pass reference |
| update_type | string | Type of update |
| changes | json | Changed data |
| pushed | boolean | Push sent |
| pushed_at | timestamp | When pushed |

---

## Events

The microservice dispatches these events:

- `WalletPassGenerated` - When a new pass is created
- `WalletPassUpdated` - When a pass is updated
- `WalletPassVoided` - When a pass is voided

---

## Troubleshooting

### Pass won't add to Apple Wallet

1. Check certificate is valid and not expired
2. Verify pass type identifier matches certificate
3. Ensure all required images are included
4. Check signature is valid

### Google Wallet URL returns error

1. Verify service account has correct permissions
2. Check issuer ID is correct
3. Ensure JWT signing key is valid

### Push notifications not working

1. Verify APNs certificate is configured
2. Check device is registered
3. Ensure push token is valid

---

## Best Practices

1. **Generate passes immediately** after purchase for best UX
2. **Include pass URLs** in confirmation emails
3. **Update passes** when event details change
4. **Void passes** when tickets are cancelled
5. **Use tenant branding** for colors and logo
6. **Test on real devices** before production

---

## Pricing

**€8/month** per tenant

Includes:
- Unlimited pass generation
- Both Apple and Google platforms
- Push notification support
- Automatic updates

---

**Version:** 1.0.0
**Last Updated:** 2025-11-20
