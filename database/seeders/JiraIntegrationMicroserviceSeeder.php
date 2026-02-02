<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JiraIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'jira-integration'],
            [
                'name' => json_encode(['en' => 'Jira Integration', 'ro' => 'Integrare Jira']),
                'description' => json_encode([
                    'en' => 'Connect with Atlassian Jira for project management. Create issues automatically for support tickets, track event-related tasks, and sync project status with your platform.',
                    'ro' => 'Conectează-te cu Atlassian Jira pentru gestionarea proiectelor. Creează tickete automat pentru suport, urmărește task-urile legate de evenimente și sincronizează statusul proiectelor cu platforma ta.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Create and manage Jira issues',
                    'ro' => 'Creează și gestionează tickete Jira',
                ]),
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Issue creation',
                        'Issue updates and transitions',
                        'Comment management',
                        'Project syncing',
                        'JQL search queries',
                        'Webhook notifications',
                        'Status tracking',
                        'OAuth 2.0 authentication',
                        'Multiple project support',
                    ],
                    'ro' => [
                        'Creare tickete',
                        'Actualizări și tranziții tickete',
                        'Gestionare comentarii',
                        'Sincronizare proiecte',
                        'Query-uri de căutare JQL',
                        'Notificări webhook',
                        'Urmărire status',
                        'Autentificare OAuth 2.0',
                        'Suport proiecte multiple',
                    ],
                ]),
                'category' => 'project-management',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'database_tables' => ['jira_connections', 'jira_projects', 'jira_issues', 'jira_webhooks'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Jira Integration microservice seeded successfully');
    }
}
