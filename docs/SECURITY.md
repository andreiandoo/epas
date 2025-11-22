# Security Features

This document describes the security features implemented in the EPAS microservices infrastructure.

## Table of Contents

1. [Admin Authentication](#admin-authentication)
2. [Rate Limiting](#rate-limiting)
3. [Webhook Signature Verification](#webhook-signature-verification)
4. [API Key Security](#api-key-security)
5. [Audit Logging](#audit-logging)

---

## Admin Authentication

Administrative endpoints (`/api/admin/*`) are protected with the `admin.auth` middleware.

### Configuration

Set admin users via environment variables:

```env
# Method 1: By user ID (recommended for production)
MICROSERVICES_ADMIN_USER_IDS=1,2,3

# Method 2: By email domain (for development)
MICROSERVICES_ADMIN_DOMAINS=admin.company.com,company.com
```

### Authentication Methods

The `AuthenticateAdmin` middleware checks admin status in this order:

1. **Role Column**: Checks if `users.role` is 'admin' or 'super_admin'
2. **Boolean Flag**: Checks if `users.is_admin` is true
3. **Email Domain**: Checks if email domain is in allowed list (dev only)
4. **User ID**: Checks if user ID is in admin list

### Usage

The middleware is automatically applied to all `/api/admin/*` routes.

### Response Codes

- `401 Unauthorized` - User is not authenticated
- `403 Forbidden` - User is authenticated but not an admin
- `200 OK` - User is authenticated and authorized

---

## Rate Limiting

All API endpoints include rate limit information in response headers.

### Rate Limit Headers

Every API response includes:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 847
X-RateLimit-Reset: 1700000000
```

- **X-RateLimit-Limit**: Maximum requests allowed per hour
- **X-RateLimit-Remaining**: Requests remaining in current window
- **X-RateLimit-Reset**: Unix timestamp when the limit resets

### Configuration

Default rate limits are set per API key:

```env
MICROSERVICES_API_DEFAULT_RATE_LIMIT=1000
```

Individual API keys can have custom rate limits set when created.

### Rate Limit Response

When rate limit is exceeded (HTTP 429):

```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "You have exceeded the rate limit of 1000 requests per hour",
  "retry_after": 3600
}
```

### Implementation

The `AddRateLimitHeaders` middleware is automatically applied to all API routes.

---

## Webhook Signature Verification

Incoming webhooks are verified to prevent spoofing attacks.

### Supported Providers

1. **Twilio** (WhatsApp)
2. **Stripe** (Payments)
3. **GitHub** (Repository events)
4. **Custom** (HMAC-based)

### Twilio Webhook Verification

Automatically applied to WhatsApp webhooks:

```php
Route::post('/webhook', [WhatsAppController::class, 'webhook'])
    ->middleware('webhook.verify:twilio');
```

**Configuration:**
```env
WHATSAPP_TWILIO_AUTH_TOKEN=your_auth_token_here
```

The middleware verifies the `X-Twilio-Signature` header using the URL and POST parameters.

### Stripe Webhook Verification

For Stripe webhooks:

```php
Route::post('/stripe/webhook', [StripeController::class, 'webhook'])
    ->middleware('webhook.verify:stripe');
```

**Configuration:**
```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

Verifies the `Stripe-Signature` header with timestamp tolerance (5 minutes).

### GitHub Webhook Verification

For GitHub webhooks:

```php
Route::post('/github/webhook', [GitHubController::class, 'webhook'])
    ->middleware('webhook.verify:github');
```

**Configuration:**
```env
GITHUB_WEBHOOK_SECRET=your_secret_here
```

Verifies the `X-Hub-Signature-256` header.

### Custom HMAC Verification

For custom webhooks:

```php
Route::post('/custom/webhook', [CustomController::class, 'webhook'])
    ->middleware('webhook.verify:custom');
```

**Configuration:**
```env
WEBHOOKS_SIGNATURE_SECRET=your_secret_here
```

Checks these headers (in order):
- `X-Signature`
- `X-Webhook-Signature`
- `X-Hub-Signature`

### Manual Verification

You can manually verify signatures using the `WebhookSignatureVerifier` service:

```php
use App\Services\Webhooks\WebhookSignatureVerifier;

$verifier = app(WebhookSignatureVerifier::class);

// Twilio
$isValid = $verifier->verifyTwilio($url, $params, $signature, $authToken);

// Stripe
$isValid = $verifier->verifyStripe($payload, $signature, $secret);

// GitHub
$isValid = $verifier->verifyGitHub($payload, $signature, $secret);

// Custom HMAC
$isValid = $verifier->verifyHmac($payload, $signature, $secret);
```

### Generating Signatures for Outgoing Webhooks

When sending webhooks to other services:

```php
$verifier = app(WebhookSignatureVerifier::class);

$signature = $verifier->generateHmac($payload, $secret);
// Or with base64 encoding:
$signature = $verifier->generateHmacBase64($payload, $secret);
```

### Security Considerations

1. **Always verify signatures in production**
2. **Keep webhook secrets secure** (use environment variables)
3. **Rotate secrets periodically**
4. **Use HTTPS for webhook endpoints**
5. **Implement replay attack prevention** (Stripe does this with timestamps)

---

## API Key Security

### Key Generation

API keys are generated with cryptographic randomness:

- Format: `epas_` + 40 random characters
- Stored as SHA256 hash (never in plain text)
- Shown only once during creation

### Key Features

1. **Scoped Permissions**: Restrict access to specific operations
2. **Rate Limiting**: Per-key rate limits
3. **IP Whitelisting**: Optional IP restrictions
4. **Expiration**: Keys can have expiration dates
5. **Revocation**: Instant key revocation

### Best Practices

1. **Rotate keys regularly** (every 90 days)
2. **Use minimum required scopes**
3. **Enable IP whitelisting for production**
4. **Monitor usage via metrics**
5. **Revoke compromised keys immediately**

### Key Storage

```
✅ DO:
- Store in environment variables
- Use secrets management (AWS Secrets Manager, Vault)
- Rotate regularly

❌ DON'T:
- Commit to version control
- Share in plain text
- Reuse across environments
```

---

## Audit Logging

All security-relevant actions are logged to the audit trail.

### Logged Actions

- API key creation/revocation
- Admin authentication attempts
- Configuration changes
- Webhook configuration changes
- Feature flag modifications
- Microservice activation/deactivation

### Audit Log Fields

```php
[
    'id' => 'uuid',
    'tenant_id' => 'uuid',
    'actor_type' => 'user|api_key|system',
    'actor_id' => 'uuid',
    'actor_name' => 'string',
    'action' => 'api_key.created',
    'resource_type' => 'api_key',
    'resource_id' => 'uuid',
    'metadata' => [...],
    'changes' => ['old' => ..., 'new' => ...],
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...',
    'severity' => 'low|medium|high|critical',
    'created_at' => '2025-11-16 12:00:00',
]
```

### Viewing Audit Logs

**Via API:**
```bash
GET /api/v1/tenant/audit?severity=critical&limit=50
```

**Via CLI:**
```bash
php artisan audit:view --severity=critical --limit=50
```

### Retention

Audit logs are retained for 365 days by default:

```env
MICROSERVICES_AUDIT_RETENTION_DAYS=365
```

Cleanup runs automatically via scheduled task at 4 AM daily.

---

## Security Checklist

### Production Deployment

- [ ] Set admin user IDs: `MICROSERVICES_ADMIN_USER_IDS`
- [ ] Configure alert recipients: `ALERTS_DEFAULT_RECIPIENTS`
- [ ] Set webhook secrets (Twilio, Stripe, etc.)
- [ ] Enable signature verification: `WEBHOOKS_VERIFY_SIGNATURES=true`
- [ ] Configure API rate limits
- [ ] Enable audit logging: `MICROSERVICES_AUDIT_ENABLED=true`
- [ ] Set up HTTPS/TLS
- [ ] Configure firewall rules
- [ ] Enable detailed API usage tracking (optional)
- [ ] Set up monitoring alerts

### Regular Maintenance

- [ ] Review audit logs weekly
- [ ] Rotate API keys every 90 days
- [ ] Update webhook secrets quarterly
- [ ] Review admin user list monthly
- [ ] Check rate limit violations
- [ ] Monitor failed authentication attempts
- [ ] Review and revoke unused API keys

---

## Incident Response

### Compromised API Key

1. **Revoke immediately** via API or CLI:
   ```bash
   php artisan tenant:revoke-api-key {key_id}
   ```

2. **Check audit logs** for unauthorized usage:
   ```bash
   php artisan audit:view --resource-id={key_id} --limit=100
   ```

3. **Generate new key** with different permissions
4. **Notify affected tenant**
5. **Update systems** with new key

### Suspicious Webhook Activity

1. **Check webhook delivery logs**
2. **Verify signature verification is enabled**
3. **Rotate webhook secret**
4. **Update provider configuration**
5. **Monitor for retry attempts**

### Unauthorized Admin Access

1. **Review audit logs** for admin actions
2. **Check admin user list**
3. **Revoke compromised credentials**
4. **Reset passwords** for affected users
5. **Enable 2FA** (if available)

---

## Support

For security issues or questions:
- **Email**: security@epas.com
- **Documentation**: https://docs.epas.com/security
- **Report vulnerabilities**: security@epas.com (PGP key available)
