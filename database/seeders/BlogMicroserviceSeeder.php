<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $this->command->info('✓ Blog microservice metadata seeded successfully');
    }
}
