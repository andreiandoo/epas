# EPAS Microservices API Documentation

Welcome to the EPAS Microservices API documentation. This API provides comprehensive access to microservices infrastructure including WhatsApp, eFactura, Accounting, Insurance, and more.

## Base URL

**Production:** `https://api.epas.com/api`
**Development:** `http://localhost/api`

## Authentication

All API endpoints require authentication using an API key. Include your API key in the request header:

```
X-API-Key: your_api_key_here
```

Or as a query parameter:

```
?api_key=your_api_key_here
```

### Getting an API Key

1. **Via Dashboard:** Navigate to Settings → API Keys in your tenant dashboard
2. **Via CLI:** Use the Artisan command:
   ```bash
   php artisan tenant:generate-api-key {tenant_id} --name="My API Key"
   ```

## Rate Limiting

- Default rate limit: **1000 requests/hour per API key**
- Rate limit headers are included in all responses:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Requests remaining
  - `X-RateLimit-Reset`: Time when limit resets (Unix timestamp)

When rate limit is exceeded, you'll receive a `429 Too Many Requests` response:

```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "You have exceeded the rate limit of 1000 requests per hour",
  "retry_after": 3600
}
```

## Permission Scopes

API keys can be restricted to specific operations using scopes:

| Scope | Description |
|-------|-------------|
| `*` | Full access to all microservices |
| `whatsapp:send` | Send WhatsApp messages |
| `whatsapp:templates` | Manage WhatsApp templates |
| `whatsapp:*` | Full WhatsApp access |
| `efactura:submit` | Submit invoices to ANAF |
| `efactura:status` | Check invoice status |
| `efactura:*` | Full eFactura access |
| `accounting:invoice` | Create accounting invoices |
| `accounting:export` | Export accounting data |
| `accounting:*` | Full accounting access |
| `insurance:quote` | Generate insurance quotes |
| `insurance:policy` | Manage insurance policies |
| `insurance:*` | Full insurance access |
| `webhooks:manage` | Manage webhook configurations |
| `metrics:read` | Read microservice metrics |

## Quick Start

### 1. Generate an API Key

```bash
php artisan tenant:generate-api-key abc-123-tenant-id \
  --name="Production API Key" \
  --scopes=whatsapp:send,efactura:submit \
  --rate-limit=2000
```

### 2. Make Your First Request

```bash
curl -X GET "https://api.epas.com/api/v1/tenant/subscriptions" \
  -H "X-API-Key: your_api_key_here"
```

### 3. View Your Usage

```bash
curl -X GET "https://api.epas.com/api/v1/tenant/metrics/summary" \
  -H "X-API-Key: your_api_key_here"
```

## API Endpoints

### Tenant API (v1)

All tenant endpoints are prefixed with `/v1/tenant/` and require API key authentication.

#### API Key Management
- `GET /v1/tenant/api-keys` - List all API keys
- `POST /v1/tenant/api-keys` - Create a new API key
- `GET /v1/tenant/api-keys/scopes` - Get available scopes
- `GET /v1/tenant/api-keys/{keyId}` - Get API key details
- `PUT /v1/tenant/api-keys/{keyId}` - Update API key
- `DELETE /v1/tenant/api-keys/{keyId}` - Revoke API key
- `GET /v1/tenant/api-keys/{keyId}/usage` - Get usage statistics

#### Audit Logs
- `GET /v1/tenant/audit` - Get audit logs
- `GET /v1/tenant/audit/actions` - Get available action types

#### Health Monitoring
- `GET /v1/tenant/health` - Get overall system health
- `GET /v1/tenant/health/{service}` - Check specific service

#### Usage Metrics
- `GET /v1/tenant/metrics` - Get usage metrics
- `GET /v1/tenant/metrics/summary` - Get metrics summary
- `GET /v1/tenant/metrics/breakdown` - Get usage breakdown

#### Subscriptions
- `GET /v1/tenant/subscriptions` - Get active subscriptions
- `GET /v1/tenant/subscriptions/catalog` - Get microservices catalog
- `GET /v1/tenant/subscriptions/{id}` - Get subscription details

### Admin API

Administrative endpoints for system monitoring (require admin authentication).

#### System Health
- `GET /admin/health` - System health overview
- `GET /admin/health/history` - Health history
- `GET /admin/health/{service}` - Service-specific health

#### Alert Management
- `GET /admin/alerts/config` - Get alert configuration
- `POST /admin/alerts/test` - Test alert delivery

#### Audit Logs
- `GET /admin/audit` - Get audit logs
- `GET /admin/audit/stats` - Get audit statistics
- `GET /admin/audit/export` - Export audit logs

#### API Usage Monitoring
- `GET /admin/api-usage` - API usage overview
- `GET /admin/api-usage/by-tenant` - Usage by tenant
- `GET /admin/api-usage/rate-limit-violations` - Rate limit violations
- `GET /admin/api-usage/{keyId}` - API key usage details

## Health Check Endpoints

For monitoring tools and uptime services:

### Simple Ping
```bash
GET /api/ping
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2025-11-16T12:00:00Z"
}
```

### Health Check (Simple)
```bash
GET /api/health?detailed=false
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-11-16T12:00:00Z"
}
```

### Health Check (Detailed)
```bash
GET /api/health?detailed=true
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-11-16T12:00:00Z",
  "checks": {
    "app": {
      "status": "healthy"
    },
    "database": {
      "status": "healthy"
    },
    "cache": {
      "status": "healthy"
    }
  }
}
```

## CLI Commands

EPAS provides several Artisan commands for managing the microservices infrastructure:

### Generate API Key
```bash
php artisan tenant:generate-api-key {tenant_id} \
  --name="API Key Name" \
  --scopes=whatsapp:send,efactura:submit \
  --rate-limit=1000 \
  --expires=2025-12-31
```

### View Audit Logs
```bash
# View all logs
php artisan audit:view

# Filter by tenant
php artisan audit:view --tenant=abc-123

# Filter by action and severity
php artisan audit:view --action=api_key.created --severity=high

# Limit results
php artisan audit:view --limit=100
```

### Warm Caches
```bash
# Warm all caches
php artisan cache:warm-microservices

# Warm specific tenant
php artisan cache:warm-microservices --tenant=abc-123

# Warm global caches only
php artisan cache:warm-microservices --global
```

### Check System Health
```bash
# Simple health check
php artisan health:check

# Detailed health check
php artisan health:check --verbose

# Check specific service
php artisan health:check --service=whatsapp
```

## Error Handling

All errors follow a consistent format:

```json
{
  "success": false,
  "error": "Error type",
  "message": "Detailed error message"
}
```

### Common HTTP Status Codes

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Missing or invalid API key
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation error
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error
- `503 Service Unavailable` - Service is unhealthy

## OpenAPI Specification

The complete API specification is available in OpenAPI 3.0 format:

**JSON:** `/docs/api-docs.json`

You can use this with tools like:
- Swagger UI
- Postman
- Insomnia
- Any OpenAPI-compatible tool

## Best Practices

1. **Store API Keys Securely**
   - Never commit API keys to version control
   - Use environment variables
   - Rotate keys regularly

2. **Handle Rate Limits**
   - Check `X-RateLimit-Remaining` header
   - Implement exponential backoff on 429 responses
   - Consider caching responses

3. **Error Handling**
   - Always check the `success` field
   - Log errors for debugging
   - Implement retries for transient failures

4. **IP Whitelisting**
   - Restrict API keys to known IPs in production
   - Use separate keys for different environments

5. **Monitoring**
   - Monitor your API usage via metrics endpoints
   - Set up alerts for unusual patterns
   - Review audit logs regularly

## Support

For API support or questions:
- Email: api@epas.com
- Documentation: https://docs.epas.com
- Status Page: https://status.epas.com

## Changelog

### Version 1.0.0 (2025-11-16)
- Initial release
- API key authentication
- Tenant self-service endpoints
- Admin monitoring endpoints
- Health check system
- Audit logging
- Usage metrics
- OpenAPI documentation
