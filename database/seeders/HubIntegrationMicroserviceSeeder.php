<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HubIntegrationMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert Hub Integration microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'hub-integration'],
            [
                'name' => json_encode(['en' => 'Hub Integration', 'ro' => 'Hub Integrări']),
                'description' => json_encode([
                    'en' => 'Unified integration hub connecting your platform with external services. Manage OAuth connections, API integrations, webhooks, and data synchronization with popular business tools like Slack, Discord, Google Workspace, Microsoft 365, Salesforce, HubSpot, Jira, Twilio, and Zapier.',
                    'ro' => 'Hub de integrare unificat care conectează platforma ta cu servicii externe. Gestionează conexiuni OAuth, integrări API, webhookuri și sincronizarea datelor cu instrumente populare precum Slack, Discord, Google Workspace, Microsoft 365, Salesforce, HubSpot, Jira, Twilio și Zapier.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Connect with external services and automate workflows',
                    'ro' => 'Conectează-te cu servicii externe și automatizează fluxurile de lucru',
                ]),
                'price' => 35.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Pre-built connectors for 9+ popular services',
                        'Slack integration (messages, channels, reactions)',
                        'Discord integration (webhooks, bot messaging)',
                        'Google Workspace (Drive, Calendar, Gmail)',
                        'Microsoft 365 (OneDrive, Outlook, Teams)',
                        'Salesforce CRM integration',
                        'HubSpot CRM integration',
                        'Jira project management integration',
                        'Twilio SMS and WhatsApp messaging',
                        'Zapier webhook automation',
                        'OAuth 2.0 secure authentication',
                        'Encrypted credential storage',
                        'Webhook endpoint management',
                        'Automatic token refresh',
                        'Event logging and audit trail',
                        'Data sync job scheduling',
                        'Custom field mapping',
                        'Error handling and retry logic',
                    ],
                    'ro' => [
                        'Conectori pre-construiți pentru 9+ servicii populare',
                        'Integrare Slack (mesaje, canale, reacții)',
                        'Integrare Discord (webhookuri, mesaje bot)',
                        'Google Workspace (Drive, Calendar, Gmail)',
                        'Microsoft 365 (OneDrive, Outlook, Teams)',
                        'Integrare CRM Salesforce',
                        'Integrare CRM HubSpot',
                        'Integrare management proiect Jira',
                        'Mesagerie SMS și WhatsApp prin Twilio',
                        'Automatizare webhook Zapier',
                        'Autentificare securizată OAuth 2.0',
                        'Stocare criptată a credențialelor',
                        'Gestionare endpoint-uri webhook',
                        'Reîmprospătare automată token',
                        'Jurnal evenimente și audit',
                        'Programare joburi sincronizare date',
                        'Mapare câmpuri personalizată',
                        'Gestionare erori și logică retry',
                    ],
                ]),
                'category' => 'integration',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'GET /api/hub/connectors',
                        'GET /api/hub/connectors/{slug}',
                        'POST /api/hub/connections',
                        'GET /api/hub/connections',
                        'GET /api/hub/connections/{id}',
                        'DELETE /api/hub/connections/{id}',
                        'POST /api/hub/connections/{id}/test',
                        'POST /api/hub/connections/{id}/action',
                        'GET /api/hub/oauth/callback',
                        'POST /api/hub/webhooks',
                        'GET /api/hub/events',
                    ],
                    'supported_connectors' => [
                        'slack',
                        'discord',
                        'google-workspace',
                        'microsoft-365',
                        'salesforce',
                        'hubspot',
                        'jira',
                        'twilio',
                        'zapier',
                    ],
                    'database_tables' => [
                        'hub_connectors',
                        'hub_connections',
                        'hub_events',
                        'hub_webhook_endpoints',
                        'hub_sync_jobs',
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Seed available connectors
        $connectors = [
            [
                'slug' => 'slack',
                'name' => json_encode(['en' => 'Slack', 'ro' => 'Slack']),
                'description' => json_encode([
                    'en' => 'Send messages, create channels, and manage workspace communications',
                    'ro' => 'Trimite mesaje, creează canale și gestionează comunicările workspace',
                ]),
                'icon' => 'slack',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://slack.com/oauth/v2/authorize',
                    'token_url' => 'https://slack.com/api/oauth.v2.access',
                    'default_scopes' => ['chat:write', 'channels:read', 'users:read'],
                ]),
                'supported_actions' => json_encode([
                    'send_message' => 'Send a message to a channel',
                    'create_channel' => 'Create a new channel',
                    'list_channels' => 'List all channels',
                    'add_reaction' => 'Add emoji reaction to a message',
                    'upload_file' => 'Upload a file to a channel',
                ]),
                'supported_events' => json_encode([
                    'message' => 'New message posted',
                    'channel_created' => 'Channel was created',
                    'member_joined_channel' => 'Member joined a channel',
                ]),
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'discord',
                'name' => json_encode(['en' => 'Discord', 'ro' => 'Discord']),
                'description' => json_encode([
                    'en' => 'Send messages to Discord channels via webhooks or bot integration',
                    'ro' => 'Trimite mesaje în canalele Discord prin webhookuri sau integrare bot',
                ]),
                'icon' => 'discord',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://discord.com/api/oauth2/authorize',
                    'token_url' => 'https://discord.com/api/oauth2/token',
                    'default_scopes' => ['bot', 'guilds', 'guilds.members.read'],
                ]),
                'supported_actions' => json_encode([
                    'send_webhook_message' => 'Send message via webhook',
                    'send_bot_message' => 'Send message via bot',
                    'list_guilds' => 'List available guilds',
                    'list_channels' => 'List guild channels',
                    'create_invite' => 'Create server invite',
                ]),
                'supported_events' => json_encode([
                    'MESSAGE_CREATE' => 'New message created',
                    'GUILD_MEMBER_ADD' => 'Member joined guild',
                ]),
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'google-workspace',
                'name' => json_encode(['en' => 'Google Workspace', 'ro' => 'Google Workspace']),
                'description' => json_encode([
                    'en' => 'Integrate with Google Drive, Calendar, and Gmail',
                    'ro' => 'Integrează-te cu Google Drive, Calendar și Gmail',
                ]),
                'icon' => 'google',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                    'token_url' => 'https://oauth2.googleapis.com/token',
                    'default_scopes' => [
                        'https://www.googleapis.com/auth/drive',
                        'https://www.googleapis.com/auth/calendar',
                        'https://www.googleapis.com/auth/gmail.send',
                    ],
                ]),
                'supported_actions' => json_encode([
                    'upload_file' => 'Upload file to Drive',
                    'list_files' => 'List Drive files',
                    'create_event' => 'Create calendar event',
                    'list_events' => 'List calendar events',
                    'send_email' => 'Send email via Gmail',
                ]),
                'supported_events' => json_encode([
                    'file_created' => 'File was created',
                    'file_modified' => 'File was modified',
                    'calendar_event_created' => 'Calendar event created',
                ]),
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'microsoft-365',
                'name' => json_encode(['en' => 'Microsoft 365', 'ro' => 'Microsoft 365']),
                'description' => json_encode([
                    'en' => 'Connect with OneDrive, Outlook, and Microsoft Teams',
                    'ro' => 'Conectează-te cu OneDrive, Outlook și Microsoft Teams',
                ]),
                'icon' => 'microsoft',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                    'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                    'default_scopes' => [
                        'Files.ReadWrite',
                        'Mail.Send',
                        'Calendars.ReadWrite',
                        'Team.ReadBasic.All',
                    ],
                ]),
                'supported_actions' => json_encode([
                    'upload_file' => 'Upload to OneDrive',
                    'send_email' => 'Send Outlook email',
                    'create_event' => 'Create calendar event',
                    'send_teams_message' => 'Send Teams message',
                    'list_teams' => 'List Teams',
                ]),
                'supported_events' => json_encode([
                    'file_created' => 'File created in OneDrive',
                    'email_received' => 'Email received',
                    'teams_message' => 'Teams message received',
                ]),
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'slug' => 'salesforce',
                'name' => json_encode(['en' => 'Salesforce', 'ro' => 'Salesforce']),
                'description' => json_encode([
                    'en' => 'Sync contacts, leads, and opportunities with Salesforce CRM',
                    'ro' => 'Sincronizează contacte, leaduri și oportunități cu Salesforce CRM',
                ]),
                'icon' => 'salesforce',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://login.salesforce.com/services/oauth2/authorize',
                    'token_url' => 'https://login.salesforce.com/services/oauth2/token',
                    'default_scopes' => ['api', 'refresh_token'],
                ]),
                'supported_actions' => json_encode([
                    'create_contact' => 'Create a contact',
                    'update_contact' => 'Update a contact',
                    'create_lead' => 'Create a lead',
                    'convert_lead' => 'Convert lead to contact',
                    'create_opportunity' => 'Create an opportunity',
                    'query' => 'Run SOQL query',
                ]),
                'supported_events' => json_encode([
                    'contact_created' => 'Contact was created',
                    'lead_created' => 'Lead was created',
                    'opportunity_updated' => 'Opportunity was updated',
                ]),
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'slug' => 'hubspot',
                'name' => json_encode(['en' => 'HubSpot', 'ro' => 'HubSpot']),
                'description' => json_encode([
                    'en' => 'Manage contacts, deals, and companies in HubSpot CRM',
                    'ro' => 'Gestionează contacte, deal-uri și companii în HubSpot CRM',
                ]),
                'icon' => 'hubspot',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://app.hubspot.com/oauth/authorize',
                    'token_url' => 'https://api.hubapi.com/oauth/v1/token',
                    'default_scopes' => [
                        'crm.objects.contacts.read',
                        'crm.objects.contacts.write',
                        'crm.objects.deals.read',
                        'crm.objects.deals.write',
                    ],
                ]),
                'supported_actions' => json_encode([
                    'create_contact' => 'Create a contact',
                    'update_contact' => 'Update a contact',
                    'search_contacts' => 'Search contacts',
                    'create_deal' => 'Create a deal',
                    'create_company' => 'Create a company',
                ]),
                'supported_events' => json_encode([
                    'contact.creation' => 'Contact was created',
                    'contact.propertyChange' => 'Contact property changed',
                    'deal.creation' => 'Deal was created',
                ]),
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'slug' => 'jira',
                'name' => json_encode(['en' => 'Jira', 'ro' => 'Jira']),
                'description' => json_encode([
                    'en' => 'Create and manage issues, projects, and workflows in Jira',
                    'ro' => 'Creează și gestionează tickete, proiecte și fluxuri în Jira',
                ]),
                'icon' => 'jira',
                'auth_type' => 'oauth2',
                'auth_config' => json_encode([
                    'authorize_url' => 'https://auth.atlassian.com/authorize',
                    'token_url' => 'https://auth.atlassian.com/oauth/token',
                    'default_scopes' => [
                        'read:jira-work',
                        'write:jira-work',
                        'read:jira-user',
                        'offline_access',
                    ],
                ]),
                'supported_actions' => json_encode([
                    'create_issue' => 'Create a new issue',
                    'update_issue' => 'Update an existing issue',
                    'transition_issue' => 'Transition issue status',
                    'add_comment' => 'Add comment to issue',
                    'search_issues' => 'Search issues with JQL',
                    'list_projects' => 'List all projects',
                ]),
                'supported_events' => json_encode([
                    'jira:issue_created' => 'Issue was created',
                    'jira:issue_updated' => 'Issue was updated',
                    'comment_created' => 'Comment was added',
                ]),
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'slug' => 'twilio',
                'name' => json_encode(['en' => 'Twilio', 'ro' => 'Twilio']),
                'description' => json_encode([
                    'en' => 'Send SMS, WhatsApp messages, and make voice calls',
                    'ro' => 'Trimite SMS-uri, mesaje WhatsApp și efectuează apeluri vocale',
                ]),
                'icon' => 'twilio',
                'auth_type' => 'api_key',
                'auth_config' => json_encode([
                    'required_fields' => ['account_sid', 'auth_token', 'phone_number'],
                ]),
                'supported_actions' => json_encode([
                    'send_sms' => 'Send an SMS message',
                    'send_whatsapp' => 'Send a WhatsApp message',
                    'make_call' => 'Initiate a voice call',
                    'list_messages' => 'List sent/received messages',
                ]),
                'supported_events' => json_encode([
                    'message.delivered' => 'Message was delivered',
                    'message.received' => 'Incoming message received',
                    'call.completed' => 'Call was completed',
                ]),
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'slug' => 'zapier',
                'name' => json_encode(['en' => 'Zapier', 'ro' => 'Zapier']),
                'description' => json_encode([
                    'en' => 'Connect to thousands of apps through Zapier webhooks',
                    'ro' => 'Conectează-te la mii de aplicații prin webhookuri Zapier',
                ]),
                'icon' => 'zapier',
                'auth_type' => 'webhook',
                'auth_config' => json_encode([
                    'required_fields' => ['webhook_url'],
                ]),
                'supported_actions' => json_encode([
                    'trigger_zap' => 'Trigger a Zap webhook',
                    'subscribe_hook' => 'Subscribe to REST hook',
                    'unsubscribe_hook' => 'Unsubscribe from REST hook',
                ]),
                'supported_events' => json_encode([
                    'order_created' => 'Order was created',
                    'customer_created' => 'Customer was created',
                    'event_published' => 'Event was published',
                    'ticket_sold' => 'Ticket was sold',
                ]),
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($connectors as $connector) {
            DB::table('hub_connectors')->updateOrInsert(
                ['slug' => $connector['slug']],
                array_merge($connector, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Hub Integration microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - ' . count($connectors) . ' connector definitions created');
    }
}
