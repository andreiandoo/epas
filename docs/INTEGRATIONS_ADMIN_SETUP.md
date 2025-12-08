# Integration Microservices - Admin Setup Guide

This document provides step-by-step instructions for core admins to configure each integration microservice before tenants can use them.

---

## Table of Contents

1. [Slack Integration](#1-slack-integration)
2. [Discord Integration](#2-discord-integration)
3. [Google Workspace Integration](#3-google-workspace-integration)
4. [Microsoft 365 Integration](#4-microsoft-365-integration)
5. [Salesforce Integration](#5-salesforce-integration)
6. [HubSpot Integration](#6-hubspot-integration)
7. [Jira Integration](#7-jira-integration)
8. [Twilio Integration](#8-twilio-integration)
9. [Zapier Integration](#9-zapier-integration)
10. [Google Sheets Integration](#10-google-sheets-integration)
11. [WhatsApp Business Cloud API](#11-whatsapp-business-cloud-api)
12. [Telegram Bot Integration](#12-telegram-bot-integration)
13. [Airtable Integration](#13-airtable-integration)
14. [Square Integration](#14-square-integration)
15. [Zoom Integration](#15-zoom-integration)
16. [Facebook Conversions API](#16-facebook-conversions-api)

---

## 1. Slack Integration

### Admin Setup Steps

1. **Create Slack App**
   - Go to [Slack API](https://api.slack.com/apps)
   - Click "Create New App" → "From scratch"
   - Name it (e.g., "Your Platform - Tenant Integrations")
   - Select a development workspace

2. **Configure OAuth & Permissions**
   - Navigate to "OAuth & Permissions"
   - Add Redirect URL: `https://yourdomain.com/integrations/slack/callback`
   - Add Bot Token Scopes:
     - `channels:read` - View basic channel info
     - `chat:write` - Send messages
     - `files:write` - Upload files
     - `users:read` - View user info
     - `incoming-webhook` - Create webhooks

3. **Set Environment Variables**
   ```env
   SLACK_CLIENT_ID=your_client_id
   SLACK_CLIENT_SECRET=your_client_secret
   SLACK_REDIRECT_URI=https://yourdomain.com/integrations/slack/callback
   SLACK_SIGNING_SECRET=your_signing_secret
   ```

4. **Configure Webhooks (Optional)**
   - Go to "Event Subscriptions"
   - Enable Events
   - Set Request URL: `https://yourdomain.com/webhooks/slack`
   - Subscribe to: `message.channels`, `app_mention`

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=SlackIntegrationMicroserviceSeeder`
   - Enable microservice for tenants in admin panel

### Tenant Experience
Tenants click "Connect to Slack" → OAuth flow → Select workspace → Done. No credentials needed from tenant.

---

## 2. Discord Integration - NEFINALIZATA (nu mi s-a verificat contul)

### Admin Setup Steps

1. **Create Discord Application**
   - Go to [Discord Developer Portal](https://discord.com/developers/applications)
   - Click "New Application"
   - Name it appropriately

2. **Configure OAuth2**
   - Go to OAuth2 → General
   - Add Redirect: `https://yourdomain.com/integrations/discord/callback`
   - Copy Client ID and Client Secret

3. **Create Bot**
   - Go to "Bot" section
   - Click "Add Bot"
   - Copy Bot Token
   - Enable necessary Intents (Message Content, Server Members if needed)

4. **Set Environment Variables**
   ```env
   DISCORD_CLIENT_ID=your_client_id
   DISCORD_CLIENT_SECRET=your_client_secret
   DISCORD_REDIRECT_URI=https://yourdomain.com/integrations/discord/callback
   DISCORD_BOT_TOKEN=your_bot_token
   ```

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=DiscordIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect to Discord" → OAuth flow → Select server → Bot joins server → Done.

---

## 3. Google Workspace Integration

### Admin Setup Steps

1. **Create Google Cloud Project**
   - Go to [Google Cloud Console](https://console.cloud.google.com)
   - Create new project or select existing
   - Enable APIs: Google Drive API, Gmail API, Google Calendar API

2. **Configure OAuth Consent Screen**
   - Go to "APIs & Services" → "OAuth consent screen"
   - Select "External" user type
   - Fill app information
   - Add scopes:
     - `https://www.googleapis.com/auth/drive`
     - `https://www.googleapis.com/auth/gmail.send`
     - `https://www.googleapis.com/auth/calendar`

3. **Create OAuth Credentials**
   - Go to "Credentials" → "Create Credentials" → "OAuth client ID"
   - Application type: "Web application"
   - Add Redirect URI: `https://yourdomain.com/integrations/google-workspace/callback`

4. **Set Environment Variables**
   ```env
   GOOGLE_WORKSPACE_CLIENT_ID=your_client_id
   GOOGLE_WORKSPACE_CLIENT_SECRET=your_client_secret
   GOOGLE_WORKSPACE_REDIRECT_URI=https://yourdomain.com/integrations/google-workspace/callback
   ```

5. **Submit for Verification (Production)**
   - For production, submit OAuth consent screen for Google verification
   - This can take 2-6 weeks
   === Nu am gasit unde sa fac asta

6. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=GoogleWorkspaceIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect Google Workspace" → Google OAuth → Grant permissions → Done.

---

## 4. Microsoft 365 Integration

### Admin Setup Steps

1. **Register Azure AD Application**
   - Go to [Azure Portal](https://portal.azure.com) → Azure Active Directory
   - App registrations → New registration
   - Name the app, select "Accounts in any organizational directory and personal Microsoft accounts"

2. **Configure Authentication**
   - Go to "Authentication"
   - Add platform: Web
   - Redirect URI: `https://yourdomain.com/integrations/microsoft365/callback`
   - Enable "Access tokens" and "ID tokens"

3. **Configure API Permissions**
   - Go to "API permissions"
   - Add Microsoft Graph permissions:
     - `User.Read` - Sign in and read user profile
     - `Mail.Send` - Send mail
     - `Files.ReadWrite` - Read and write files
     - `Calendars.ReadWrite` - Read and write calendars
   - Grant admin consent if required

4. **Create Client Secret**
   - Go to "Certificates & secrets"
   - New client secret
   - Copy the value immediately (shown only once)

5. **Set Environment Variables**
   ```env
   MICROSOFT365_CLIENT_ID=your_client_id
   MICROSOFT365_CLIENT_SECRET=your_client_secret
   MICROSOFT365_REDIRECT_URI=https://yourdomain.com/integrations/microsoft365/callback
   MICROSOFT365_TENANT_ID=common
   ```

6. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=Microsoft365IntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect Microsoft 365" → Microsoft OAuth → Grant permissions → Done.

---

## 5. Salesforce Integration - NEFINALIZATA (cannot create app, sunt pe plan free)

### Admin Setup Steps

1. **Create Salesforce Connected App**
   - Go to Setup → App Manager → New Connected App
   - Fill basic info
   - Enable OAuth Settings
   - Callback URL: `https://yourdomain.com/integrations/salesforce/callback`

2. **Configure OAuth Scopes**
   - Select scopes:
     - `api` - Access and manage your data
     - `refresh_token` - Perform requests on your behalf at any time
     - `full` - Full access to your data

3. **Get Consumer Credentials**
   - After saving, click "Manage Consumer Details"
   - Copy Consumer Key and Consumer Secret

4. **Set Environment Variables**
   ```env
   SALESFORCE_CLIENT_ID=your_consumer_key
   SALESFORCE_CLIENT_SECRET=your_consumer_secret
   SALESFORCE_REDIRECT_URI=https://yourdomain.com/integrations/salesforce/callback
   ```

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=SalesforceIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect to Salesforce" → Salesforce login → Authorize → Done. Works with any Salesforce edition.

---

## 6. HubSpot Integration NETERMINATA (Use the HubSpot CLI to get started)

### Admin Setup Steps

1. **Create HubSpot App**
   - Go to [HubSpot Developers](https://developers.hubspot.com)
   - Create a developer account if needed
   - Create new app

2. **Configure OAuth**
   - Go to "Auth" tab
   - Add Redirect URL: `https://yourdomain.com/integrations/hubspot/callback`
   - Select scopes:
     - `crm.objects.contacts.read`
     - `crm.objects.contacts.write`
     - `crm.objects.deals.read`
     - `crm.objects.deals.write`
     - `crm.objects.companies.read`
     - `crm.objects.companies.write`

3. **Get Credentials**
   - Copy App ID, Client ID, and Client Secret

4. **Set Environment Variables**
   ```env
   HUBSPOT_CLIENT_ID=your_client_id
   HUBSPOT_CLIENT_SECRET=your_client_secret
   HUBSPOT_REDIRECT_URI=https://yourdomain.com/integrations/hubspot/callback
   ```

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=HubSpotIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect to HubSpot" → HubSpot OAuth → Select portal → Authorize → Done.

---

## 7. Jira Integration

### Admin Setup Steps

1. **Create Atlassian OAuth 2.0 App**
   - Go to [Atlassian Developer Console](https://developer.atlassian.com/console/myapps/)
   - Create new app (OAuth 2.0)

2. **Configure Permissions**
   - Go to "Permissions" → "Jira API"
   - Add scopes:
     - `read:jira-work` - Read project and issue data
     - `write:jira-work` - Create and edit issues
     - `read:jira-user` - Read user information

3. **Configure Authorization**
   - Go to "Authorization" → "OAuth 2.0 (3LO)"
   - Add Callback URL: `https://yourdomain.com/integrations/jira/callback`

4. **Set Environment Variables**
   ```env
   JIRA_CLIENT_ID=your_client_id
   JIRA_CLIENT_SECRET=your_client_secret
   JIRA_REDIRECT_URI=https://yourdomain.com/integrations/jira/callback
   ```

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=JiraIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect to Jira" → Atlassian OAuth → Select site → Authorize → Done.

---

## 8. Twilio Integration

### Admin Setup Steps

1. **Documentation for Tenants**
   - Twilio uses per-tenant credentials (Account SID + Auth Token)
   - No central OAuth app needed
   - Tenants must have their own Twilio accounts

2. **Webhook Configuration**
   - Set up webhook endpoint: `https://yourdomain.com/webhooks/twilio`
   - Document this URL for tenants to configure in their Twilio console

3. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=TwilioIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants enter their Twilio Account SID and Auth Token. They can get these from their Twilio Console → Account → API Credentials.

---

## 9. Zapier Integration - NETERMINATA

### Admin Setup Steps

1. **Create Zapier Integration**
   - Apply at [Zapier Developer Platform](https://developer.zapier.com)
   - Create new integration
   - Configure triggers and actions

2. **Configure Authentication**
   - Use API Key authentication (simpler) or OAuth
   - For API Key: tenants generate keys in your platform
   - For OAuth: set up OAuth endpoints

3. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=ZapierIntegrationMicroserviceSeeder`
   - Create API key generation UI for tenants

### Tenant Experience
Tenants generate an API key in your platform, then use it in Zapier to connect.

---

## 10. Google Sheets Integration

### Admin Setup Steps

1. **Create/Reuse Google Cloud Project**
   - Use same project as Google Workspace or create new
   - Enable Google Sheets API

2. **Configure OAuth**
   - Add scope: `https://www.googleapis.com/auth/spreadsheets`
   - Add Redirect URI: `https://yourdomain.com/integrations/google-sheets/callback`

3. **Set Environment Variables**
   ```env
   GOOGLE_SHEETS_CLIENT_ID=your_client_id
   GOOGLE_SHEETS_CLIENT_SECRET=your_client_secret
   GOOGLE_SHEETS_REDIRECT_URI=https://yourdomain.com/integrations/google-sheets/callback
   ```

4. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=GoogleSheetsIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect Google Sheets" → Google OAuth → Grant access → Select/create spreadsheet → Done.

---

## 11. WhatsApp Business Cloud API

### Admin Setup Steps

1. **Create Meta Business Account**
   - Go to [Meta Business Suite](https://business.facebook.com)
   - Create or use existing Business Account
   - Verify your business (required for production)

2. **Set Up WhatsApp Business Account**
   - Go to [Meta for Developers](https://developers.facebook.com)
   - Create new app → Select "Business" type
   - Add "WhatsApp" product to your app

3. **Configure WhatsApp**
   - Go to WhatsApp → Getting Started
   - Add a phone number or use the test number
   - Note the Phone Number ID and WhatsApp Business Account ID

4. **Create System User & Token**
   - Go to Business Settings → Users → System Users
   - Create new System User with Admin role
   - Add assets (your WhatsApp Business Account)
   - Generate token with permissions:
     - `whatsapp_business_messaging`
     - `whatsapp_business_management`

5. **Configure Webhooks**
   - Go to WhatsApp → Configuration
   - Callback URL: `https://yourdomain.com/webhooks/whatsapp-cloud`
   - Verify Token: Generate a secure random string
   - Subscribe to: `messages`, `message_status`, `message_template_status_update`

6. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=WhatsAppCloudIntegrationMicroserviceSeeder`

### Tenant Configuration
Each tenant needs:
- Phone Number ID (from their WhatsApp Business Account)
- WhatsApp Business Account ID
- Permanent Access Token (System User token)

### Cost Information
- **No BSP fees** - Direct Meta API usage
- Conversation-based pricing:
  - Business-initiated: ~$0.05-0.15/conversation (varies by country)
  - User-initiated: ~$0.02-0.05/conversation
  - First 1,000 service conversations/month free

---

## 12. Telegram Bot Integration

### Admin Setup Steps

1. **Documentation for Tenants**
   - Each tenant creates their own bot via [@BotFather](https://t.me/botfather)
   - No central app needed - each tenant has own bot

2. **Webhook Configuration**
   - Set up webhook endpoint: `https://yourdomain.com/webhooks/telegram/{bot_id}`
   - The service automatically registers webhooks when tenants connect

3. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=TelegramIntegrationMicroserviceSeeder`

### Tenant Experience
1. Tenant opens Telegram, messages @BotFather
2. Sends `/newbot` command
3. Names their bot
4. Receives Bot Token
5. Enters token in your platform
6. Platform verifies token and sets up webhook automatically

### Cost Information
- **Completely free** - Telegram Bot API has no usage fees
- Unlimited messages, users, and features

---

## 13. Airtable Integration

### Admin Setup Steps

1. **Create Airtable OAuth Integration**
   - Go to [Airtable Developer Hub](https://airtable.com/developers)
   - Create new OAuth integration
   - Fill in app details

2. **Configure OAuth**
   - Add Redirect URL: `https://yourdomain.com/integrations/airtable/callback`
   - Select scopes:
     - `data.records:read`
     - `data.records:write`
     - `schema.bases:read`
     - `schema.bases:write` (optional)

3. **Set Environment Variables**
   ```env
   AIRTABLE_CLIENT_ID=your_client_id
   AIRTABLE_CLIENT_SECRET=your_client_secret
   AIRTABLE_REDIRECT_URI=https://yourdomain.com/integrations/airtable/callback
   ```

4. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=AirtableIntegrationMicroserviceSeeder`

### Alternative: Personal Access Token
For simpler setups, tenants can use Personal Access Tokens:
1. Tenant goes to Airtable → Account → Developer hub
2. Creates Personal Access Token with required scopes
3. Enters token in your platform

### Tenant Experience
Option A (OAuth): Click "Connect Airtable" → Airtable OAuth → Select bases → Done
Option B (PAT): Generate token in Airtable → Enter in your platform → Done

---

## 14. Square Integration

### Admin Setup Steps

1. **Create Square Application**
   - Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
   - Create new application

2. **Configure OAuth**
   - Go to OAuth settings
   - Add Redirect URL: `https://yourdomain.com/integrations/square/callback`
   - Note Application ID and Secret

3. **Configure Permissions**
   - Select OAuth permissions:
     - `MERCHANT_PROFILE_READ`
     - `PAYMENTS_READ`
     - `PAYMENTS_WRITE`
     - `ORDERS_READ`
     - `ORDERS_WRITE`
     - `ITEMS_READ`
     - `ITEMS_WRITE`

4. **Set Up Webhooks**
   - Go to Webhooks
   - Add endpoint: `https://yourdomain.com/webhooks/square`
   - Subscribe to events:
     - `payment.completed`
     - `payment.updated`
     - `order.created`
     - `order.updated`
   - Note the Signature Key

5. **Set Environment Variables**
   ```env
   SQUARE_CLIENT_ID=your_application_id
   SQUARE_CLIENT_SECRET=your_application_secret
   SQUARE_REDIRECT_URI=https://yourdomain.com/integrations/square/callback
   SQUARE_WEBHOOK_SIGNATURE_KEY=your_signature_key
   ```

6. **Sandbox Testing**
   - Use Sandbox credentials for testing
   - Switch to Production for live merchants

7. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=SquareIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect Square" → Square OAuth → Authorize → Locations synced automatically → Done.

### Cost Information
- Square charges standard payment processing fees (2.6% + $0.10 for online)
- No additional integration fees

---

## 15. Zoom Integration

### Admin Setup Steps

1. **Create Zoom App**
   - Go to [Zoom App Marketplace](https://marketplace.zoom.us/develop/create)
   - Create OAuth App

2. **Configure OAuth**
   - App Credentials: Note Client ID and Client Secret
   - Add Redirect URL: `https://yourdomain.com/integrations/zoom/callback`
   - Add Allow List URL: `https://yourdomain.com`

3. **Configure Scopes**
   - Add scopes:
     - `meeting:read` - View meetings
     - `meeting:write` - Create/update meetings
     - `webinar:read` - View webinars
     - `webinar:write` - Create/update webinars
     - `user:read` - View user info
     - `recording:read` - View recordings

4. **Set Up Webhooks (Event Subscriptions)**
   - Go to Feature → Event Subscriptions
   - Add Event Notification Endpoint URL: `https://yourdomain.com/webhooks/zoom`
   - Add Secret Token (for signature verification)
   - Subscribe to events:
     - `meeting.started`
     - `meeting.ended`
     - `meeting.participant_joined`
     - `meeting.participant_left`
     - `webinar.registration_created`
     - `recording.completed`

5. **Set Environment Variables**
   ```env
   ZOOM_CLIENT_ID=your_client_id
   ZOOM_CLIENT_SECRET=your_client_secret
   ZOOM_REDIRECT_URI=https://yourdomain.com/integrations/zoom/callback
   ZOOM_WEBHOOK_SECRET_TOKEN=your_secret_token
   ```

6. **App Activation**
   - Submit for activation if distributing to multiple accounts
   - For internal use, activate directly

7. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=ZoomIntegrationMicroserviceSeeder`

### Tenant Experience
Tenants click "Connect Zoom" → Zoom OAuth → Authorize → Done. They can then create meetings/webinars linked to events.

### Account Requirements
- Tenants need Zoom Pro or higher for most features
- Webinar add-on required for webinar functionality
- Recordings require cloud recording enabled

---

## 16. Facebook Conversions API

### Admin Setup Steps

1. **Prerequisites**
   - Each tenant needs their own Facebook Pixel
   - Tenants need access to Facebook Events Manager

2. **Guide Tenants to Create System User**
   - Go to Business Settings → Users → System Users
   - Create System User with Admin access
   - Assign Pixel asset to System User
   - Generate Access Token with `ads_management` permission

3. **Get Required Information**
   For each tenant:
   - Pixel ID (from Events Manager → Data Sources → Pixel)
   - Access Token (System User token)
   - Optional: Business ID, Ad Account ID

4. **Test Event Setup**
   - Enable Test Mode initially
   - Use Test Event Code from Events Manager → Test Events
   - Verify events appear in Events Manager

5. **Deploy & Verify**
   - Run migrations: `php artisan migrate`
   - Run seeder: `php artisan db:seed --class=FacebookCapiIntegrationMicroserviceSeeder`

### Tenant Configuration
Each tenant provides:
- Pixel ID
- System User Access Token
- Optional: Test Event Code for testing

### Tenant Experience
1. Tenant enters Pixel ID and Access Token
2. Platform sends test event to verify connection
3. Tenant can view events in Events Manager → Test Events
4. Disable Test Mode for production

### Relationship with Meta Pixel (Client-Side)
- **Meta Pixel (existing)**: Client-side JavaScript tracking in browser
- **Conversions API (this)**: Server-side tracking from your servers
- **Best Practice**: Use BOTH for maximum accuracy
  - Same event_id for deduplication
  - Conversions API catches events blocked by ad blockers
  - Better attribution and match rates

### Cost Information
- **Free** - No cost for using Conversions API
- Improves ad performance and attribution

---

## Quick Reference: Environment Variables

```env
# Slack
SLACK_CLIENT_ID=
SLACK_CLIENT_SECRET=
SLACK_REDIRECT_URI=
SLACK_SIGNING_SECRET=

# Discord
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI=
DISCORD_BOT_TOKEN=

# Google Workspace / Sheets
GOOGLE_WORKSPACE_CLIENT_ID=
GOOGLE_WORKSPACE_CLIENT_SECRET=
GOOGLE_WORKSPACE_REDIRECT_URI=
GOOGLE_SHEETS_CLIENT_ID=
GOOGLE_SHEETS_CLIENT_SECRET=
GOOGLE_SHEETS_REDIRECT_URI=

# Microsoft 365
MICROSOFT365_CLIENT_ID=
MICROSOFT365_CLIENT_SECRET=
MICROSOFT365_REDIRECT_URI=
MICROSOFT365_TENANT_ID=common

# Salesforce
SALESFORCE_CLIENT_ID=
SALESFORCE_CLIENT_SECRET=
SALESFORCE_REDIRECT_URI=

# HubSpot
HUBSPOT_CLIENT_ID=
HUBSPOT_CLIENT_SECRET=
HUBSPOT_REDIRECT_URI=

# Jira
JIRA_CLIENT_ID=
JIRA_CLIENT_SECRET=
JIRA_REDIRECT_URI=

# Airtable
AIRTABLE_CLIENT_ID=
AIRTABLE_CLIENT_SECRET=
AIRTABLE_REDIRECT_URI=

# Square
SQUARE_CLIENT_ID=
SQUARE_CLIENT_SECRET=
SQUARE_REDIRECT_URI=
SQUARE_WEBHOOK_SIGNATURE_KEY=

# Zoom
ZOOM_CLIENT_ID=
ZOOM_CLIENT_SECRET=
ZOOM_REDIRECT_URI=
ZOOM_WEBHOOK_SECRET_TOKEN=
```

---

## Quick Reference: Migrations & Seeders

```bash
# Run all migrations
php artisan migrate

# Run individual seeders
php artisan db:seed --class=SlackIntegrationMicroserviceSeeder
php artisan db:seed --class=DiscordIntegrationMicroserviceSeeder
php artisan db:seed --class=GoogleWorkspaceIntegrationMicroserviceSeeder
php artisan db:seed --class=Microsoft365IntegrationMicroserviceSeeder
php artisan db:seed --class=SalesforceIntegrationMicroserviceSeeder
php artisan db:seed --class=HubSpotIntegrationMicroserviceSeeder
php artisan db:seed --class=JiraIntegrationMicroserviceSeeder
php artisan db:seed --class=TwilioIntegrationMicroserviceSeeder
php artisan db:seed --class=ZapierIntegrationMicroserviceSeeder
php artisan db:seed --class=GoogleSheetsIntegrationMicroserviceSeeder
php artisan db:seed --class=WhatsAppCloudIntegrationMicroserviceSeeder
php artisan db:seed --class=TelegramIntegrationMicroserviceSeeder
php artisan db:seed --class=AirtableIntegrationMicroserviceSeeder
php artisan db:seed --class=SquareIntegrationMicroserviceSeeder
php artisan db:seed --class=ZoomIntegrationMicroserviceSeeder
php artisan db:seed --class=FacebookCapiIntegrationMicroserviceSeeder
```

---

## Integration Categories Summary

| Category | Integrations |
|----------|--------------|
| **Messaging** | Slack, Discord, WhatsApp Cloud API, Telegram |
| **CRM** | Salesforce, HubSpot |
| **Productivity** | Google Workspace, Microsoft 365, Jira, Airtable, Google Sheets |
| **Communication** | Twilio, Zoom |
| **Payments** | Square |
| **Marketing** | Facebook Conversions API |
| **Automation** | Zapier |

---

## Cost Summary

| Integration | Cost to Platform | Cost to Tenant |
|-------------|------------------|----------------|
| Slack | Free | Free (Slack workspace) |
| Discord | Free | Free |
| Google Workspace | Free | Google Workspace subscription |
| Microsoft 365 | Free | Microsoft 365 subscription |
| Salesforce | Free | Salesforce subscription |
| HubSpot | Free | HubSpot subscription (free tier available) |
| Jira | Free | Jira subscription (free tier available) |
| Twilio | Free | Per-message pricing |
| Zapier | Free | Zapier subscription |
| Google Sheets | Free | Free (Google account) |
| WhatsApp Cloud API | Free | Conversation-based pricing (~$0.02-0.15) |
| Telegram | Free | Free |
| Airtable | Free | Airtable subscription (free tier available) |
| Square | Free | Standard payment processing fees |
| Zoom | Free | Zoom subscription (Pro+ for most features) |
| Facebook CAPI | Free | Free |
