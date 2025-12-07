<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert Blog/Article microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'blog'],
            [
                'name' => json_encode(['en' => 'Blog & Articles', 'ro' => 'Blog & Articole']),
                'description' => json_encode([
                    'en' => 'Complete blog and content management system for your website. Create articles, manage categories and tags, track authors, organize content into series, handle comments, and manage subscriber newsletters. Includes SEO optimization, revision history, and analytics.',
                    'ro' => 'Sistem complet de gestionare blog și conținut pentru site-ul tău. Creează articole, gestionează categorii și taguri, urmărește autori, organizează conținutul în serii, gestionează comentarii și abonați newsletter. Include optimizare SEO, istoric revizii și analiză.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Create and manage blog content with full CMS features',
                    'ro' => 'Creează și gestionează conținut blog cu funcții CMS complete',
                ]),
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Rich text editor with media embedding',
                        'Category and tag management',
                        'Multiple author support with profiles',
                        'Article series organization',
                        'Draft, scheduled, and published states',
                        'SEO metadata (title, description, keywords)',
                        'Featured articles and images',
                        'Automatic reading time calculation',
                        'Content revision history',
                        'Comment system with moderation',
                        'View tracking and analytics',
                        'Newsletter subscriptions',
                        'Related articles suggestions',
                        'Public, private, and password-protected visibility',
                        'Slug customization',
                        'Social sharing integration',
                    ],
                    'ro' => [
                        'Editor text bogat cu embedding media',
                        'Gestionare categorii și taguri',
                        'Suport autori multipli cu profiluri',
                        'Organizare articole în serii',
                        'Stări draft, programat și publicat',
                        'Metadate SEO (titlu, descriere, cuvinte cheie)',
                        'Articole și imagini featured',
                        'Calculare automată timp de citire',
                        'Istoric revizii conținut',
                        'Sistem comentarii cu moderare',
                        'Urmărire vizualizări și analiză',
                        'Abonări newsletter',
                        'Sugestii articole conexe',
                        'Vizibilitate public, privat și protejat cu parolă',
                        'Personalizare slug',
                        'Integrare partajare socială',
                    ],
                ]),
                'category' => 'content',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'GET /api/blog/articles',
                        'GET /api/blog/articles/{slug}',
                        'POST /api/blog/articles',
                        'PUT /api/blog/articles/{id}',
                        'DELETE /api/blog/articles/{id}',
                        'POST /api/blog/articles/{id}/publish',
                        'GET /api/blog/categories',
                        'POST /api/blog/categories',
                        'GET /api/blog/tags',
                        'GET /api/blog/authors',
                        'GET /api/blog/series',
                        'GET /api/blog/articles/{id}/comments',
                        'POST /api/blog/articles/{id}/comments',
                        'POST /api/blog/subscribe',
                        'GET /api/blog/stats',
                    ],
                    'database_tables' => [
                        'blog_categories',
                        'blog_tags',
                        'blog_authors',
                        'blog_series',
                        'blog_articles',
                        'blog_article_tag',
                        'blog_article_revisions',
                        'blog_article_views',
                        'blog_comments',
                        'blog_subscriptions',
                    ],
                    'max_articles' => 'unlimited',
                    'max_categories' => 100,
                    'max_authors' => 50,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create demo tenant data
        $tenantId = 1; // Demo tenant

        // Seed demo categories
        $categories = [
            [
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'News', 'ro' => 'Știri']),
                'slug' => 'news',
                'description' => json_encode(['en' => 'Latest news and announcements', 'ro' => 'Ultimele știri și anunțuri']),
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'Events', 'ro' => 'Evenimente']),
                'slug' => 'events',
                'description' => json_encode(['en' => 'Event highlights and recaps', 'ro' => 'Momente și rezumate evenimente']),
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'Tutorials', 'ro' => 'Tutoriale']),
                'slug' => 'tutorials',
                'description' => json_encode(['en' => 'How-to guides and tutorials', 'ro' => 'Ghiduri și tutoriale']),
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('blog_categories')->updateOrInsert(
                ['tenant_id' => $category['tenant_id'], 'slug' => $category['slug']],
                array_merge($category, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Seed demo tags
        $tags = [
            ['tenant_id' => $tenantId, 'name' => 'Featured', 'slug' => 'featured'],
            ['tenant_id' => $tenantId, 'name' => 'Tips', 'slug' => 'tips'],
            ['tenant_id' => $tenantId, 'name' => 'Guide', 'slug' => 'guide'],
            ['tenant_id' => $tenantId, 'name' => 'Announcement', 'slug' => 'announcement'],
            ['tenant_id' => $tenantId, 'name' => 'Community', 'slug' => 'community'],
        ];

        foreach ($tags as $tag) {
            DB::table('blog_tags')->updateOrInsert(
                ['tenant_id' => $tag['tenant_id'], 'slug' => $tag['slug']],
                array_merge($tag, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Seed demo author
        DB::table('blog_authors')->updateOrInsert(
            ['tenant_id' => $tenantId, 'slug' => 'admin'],
            [
                'tenant_id' => $tenantId,
                'name' => 'Admin',
                'slug' => 'admin',
                'email' => 'admin@example.com',
                'bio' => json_encode(['en' => 'Platform administrator and content creator', 'ro' => 'Administrator platformă și creator de conținut']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Seed demo series
        DB::table('blog_series')->updateOrInsert(
            ['tenant_id' => $tenantId, 'slug' => 'getting-started'],
            [
                'tenant_id' => $tenantId,
                'title' => json_encode(['en' => 'Getting Started Guide', 'ro' => 'Ghid de Început']),
                'slug' => 'getting-started',
                'description' => json_encode(['en' => 'A comprehensive guide to help you get started', 'ro' => 'Un ghid cuprinzător pentru a te ajuta să începi']),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Blog microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - ' . count($categories) . ' demo categories created');
        $this->command->info('  - ' . count($tags) . ' demo tags created');
        $this->command->info('  - 1 demo author created');
        $this->command->info('  - 1 demo series created');
    }
}
