<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert Knowledge Base microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'knowledge-base'],
            [
                'name' => json_encode(['en' => 'Knowledge Base', 'ro' => 'Baza de Cunoștințe']),
                'description' => json_encode([
                    'en' => 'Complete knowledge base and help center solution for your marketplace. Create articles and FAQs, organize content into categories, track helpfulness votes, and provide self-service support to your customers. Includes search functionality, popular topics, and analytics.',
                    'ro' => 'Soluție completă de bază de cunoștințe și centru de ajutor pentru marketplace-ul tău. Creează articole și FAQ-uri, organizează conținutul în categorii, urmărește voturile de utilitate și oferă suport self-service clienților. Include funcționalitate de căutare, subiecte populare și analiză.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Help center with articles, FAQs and self-service support',
                    'ro' => 'Centru de ajutor cu articole, FAQ-uri și suport self-service',
                ]),
                'price' => 10.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Rich text editor for articles',
                        'FAQ question-answer format',
                        'Category organization with icons and colors',
                        'Popular topics widget',
                        'Search functionality',
                        'Article view tracking',
                        'Helpfulness voting (Was this helpful?)',
                        'Featured articles',
                        'SEO metadata support',
                        'Multi-language content',
                        'Article tagging',
                        'Related articles',
                        'Contact section integration',
                        'Analytics dashboard',
                    ],
                    'ro' => [
                        'Editor text bogat pentru articole',
                        'Format FAQ întrebare-răspuns',
                        'Organizare categorii cu iconițe și culori',
                        'Widget subiecte populare',
                        'Funcționalitate căutare',
                        'Urmărire vizualizări articole',
                        'Votare utilitate (A fost util?)',
                        'Articole featured',
                        'Suport metadate SEO',
                        'Conținut multi-limbă',
                        'Etichetare articole',
                        'Articole conexe',
                        'Integrare secțiune contact',
                        'Dashboard analiză',
                    ],
                ]),
                'category' => 'support',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'GET /api/kb/categories',
                        'GET /api/kb/categories/{slug}',
                        'GET /api/kb/articles',
                        'GET /api/kb/articles/{slug}',
                        'GET /api/kb/articles/search',
                        'GET /api/kb/articles/popular',
                        'GET /api/kb/articles/featured',
                        'GET /api/kb/faqs',
                        'POST /api/kb/articles/{id}/vote',
                        'POST /api/kb/articles/{id}/view',
                    ],
                    'database_tables' => [
                        'kb_categories',
                        'kb_articles',
                        'kb_popular_topics',
                    ],
                    'max_articles' => 'unlimited',
                    'max_categories' => 50,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Knowledge Base microservice metadata seeded successfully');
    }
}
