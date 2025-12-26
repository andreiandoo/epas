# AmBilet.ro - Marketplace Client

This folder contains the custom website for AmBilet.ro marketplace client.

## Security Architecture

**IMPORTANT**: The API key is stored server-side and NEVER exposed to the browser.

```
Browser  →  proxy.php (server)  →  Core API
              ↓
         config.php (API key stored here, outside webroot)
```

## Folder Structure

```
ambilet/
├── api/
│   ├── config.php       # API key and configuration (KEEP OUTSIDE WEBROOT!)
│   └── proxy.php        # Server-side proxy for API calls
├── css/
│   └── styles.css       # Custom styles
├── js/
│   ├── api.js           # API client (calls local proxy, not Core directly)
│   └── app.js           # Main application logic
├── images/              # Static images
├── index.html           # Main landing page
└── README.md
```

## Deployment Setup

### 1. Recommended Server Structure

```
/var/www/ambilet.ro/
├── config/
│   └── config.php       # ← API key here (OUTSIDE webroot!)
└── public/              # ← Webroot points here
    ├── api/
    │   └── proxy.php    # Proxy script
    ├── css/
    ├── js/
    ├── images/
    └── index.html
```

### 2. Configure the API Key

Edit `config/config.php` (outside webroot):

```php
<?php
return [
    'core_api_url' => 'https://core.tixello.com/api/marketplace-client',
    'api_key' => 'mpc_YOUR_ACTUAL_API_KEY',
    'api_secret' => 'YOUR_API_SECRET',
    'allowed_origins' => [
        'https://ambilet.ro',
        'https://www.ambilet.ro',
    ],
    'rate_limit' => 60,
];
```

### 3. Update proxy.php Path

In `public/api/proxy.php`, update the config path:

```php
$configPath = dirname(__DIR__, 2) . '/config/config.php';
```

### 4. Protect config.php

Add to `.htaccess` in the config folder:

```apache
Deny from all
```

Or use nginx:

```nginx
location /config {
    deny all;
    return 404;
}
```

## How the Proxy Works

1. Browser calls `proxy.php?endpoint=events`
2. Proxy adds the API key header (stored server-side)
3. Proxy forwards request to Core API
4. Proxy returns the response to the browser

The API key never leaves your server.

## Available Endpoints

All endpoints are accessed through the proxy:

```javascript
// Browser makes request to local proxy
fetch('/api/proxy.php?endpoint=events')

// Proxy adds API key and forwards to:
// https://core.tixello.com/api/marketplace-client/events
```

| Proxy URL | Core Endpoint | Description |
|-----------|---------------|-------------|
| `?endpoint=config` | `/config` | Get client configuration |
| `?endpoint=tenants` | `/tenants` | List available tenants |
| `?endpoint=events` | `/events` | List events |
| `?endpoint=events/123` | `/events/123` | Get event details |
| `?endpoint=events/123/availability` | `/events/123/availability` | Check availability |
| `?endpoint=orders` | `/orders` | List/create orders |
| `?endpoint=orders/123` | `/orders/123` | Get order details |

## JavaScript API Client

The API client (`js/api.js`) automatically uses the proxy:

```javascript
// Initialize - no API key needed!
const api = new AmBiletAPI();

// Get events
const events = await api.getEvents({ city: 'Bucuresti' });

// Create order
const order = await api.createOrder({
    event_id: 123,
    tickets: [{ ticket_type_id: 1, quantity: 2 }],
    customer: {
        email: 'client@example.com',
        first_name: 'Ion',
        last_name: 'Popescu',
        phone: '+40712345678'
    }
});
```

## Additional Security (Core-side)

Configure these in the Core admin panel for extra security:

### IP Restriction
Limit API access to your server's IP:
```json
{
    "allowed_ips": ["1.2.3.4", "5.6.7.8/24"]
}
```

### Domain Restriction
Limit CORS to your domains:
```json
{
    "allowed_domains": ["ambilet.ro", "*.ambilet.ro"]
}
```

## Webhooks

Configure webhook URL in Core to receive order notifications:

```json
{
    "webhook_url": "https://ambilet.ro/webhooks/orders",
    "webhook_secret": "your-secret-key"
}
```

Webhook events:
- `order.created` - New order placed
- `order.confirmed` - Payment confirmed
- `order.cancelled` - Order cancelled
- `order.completed` - Order fulfilled
- `order.refunded` - Order refunded

Verify webhook signature:
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$expected = hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

## Support

Contact the Core platform team for:
- API key generation
- Tenant access configuration
- Commission rate setup
- IP/Domain whitelisting
- Technical support
