# Auto-Versioning System

This system automatically tracks and increments versions for the core application and all service modules.

## Version Format

Versions follow semantic versioning: `MAJOR.MINOR.PATCH` (e.g., `1.12.123`)

- **PATCH**: Incremented automatically on each commit (default)
- **MINOR**: Incremented for new features
- **MAJOR**: Incremented for breaking changes

## Installation

Run the installation script to set up git hooks:

```bash
./scripts/install-hooks.sh
```

## How It Works

1. When you commit changes, the `post-commit` hook automatically:
   - Detects which files were changed
   - Maps files to their respective services or core
   - Increments the patch version for affected components
   - Updates `version.json` and amends the commit

2. File mapping:
   - `app/Services/ServiceName/*` → Service version
   - `app/*` (other) → Core version
   - `config/`, `database/`, `resources/`, `routes/` → Core version

## Commands

### Show Versions

```bash
# Show all versions
php artisan version:show

# Show specific service
php artisan version:show --service=Analytics
```

### Manual Version Bump

```bash
# Bump core patch version
php artisan version:bump core

# Bump service minor version
php artisan version:bump Analytics --type=minor

# Bump major version
php artisan version:bump core --type=major
```

### Auto-Detect Changes

```bash
# Preview what would be bumped
php artisan version:auto --dry-run

# Auto-bump based on staged/committed files
php artisan version:auto
```

## Tracked Services

- Accounting
- Alerts
- Analytics
- Api
- Audit
- CRM
- Cache
- DoorSales
- EFactura
- FeatureFlags
- GroupBooking
- Health
- Insurance
- Invitations
- Metrics
- Monitoring
- PaymentProcessors
- PromoCodes
- Seating
- Stripe
- TicketCustomizer
- Tracking
- Waitlist
- Wallet
- Webhooks
- WhatsApp

## Version File

All versions are stored in `version.json` at the project root:

```json
{
    "core": "1.0.0",
    "services": {
        "Analytics": "1.0.0",
        ...
    },
    "lastUpdated": "2025-11-22T00:00:00Z"
}
```

## Disabling Auto-Versioning

To skip version bump for a specific commit:

```bash
git commit --no-verify -m "your message"
```
