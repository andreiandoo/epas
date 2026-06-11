<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class WebsiteEditorMicroserviceSeeder extends Seeder
{
    /**
     * Seed the Website Visual Editor microservice
     *
     * This microservice provides a visual website editor with theme customization
     * and page builder with drag-and-drop blocks for tenant websites.
     *
     * Price: 50 EUR one-time payment
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'website-visual-editor'],
            [
                'name' => [
                    'en' => 'Website Visual Editor',
                    'ro' => 'Editor Vizual Website',
                ],
                'description' => [
                    'en' => 'Complete visual website editor with theme customization and drag-and-drop page builder. Customize colors, fonts, layouts, and build custom pages with 25+ block types including event grids, countdown timers, sliders, galleries, and more.',
                    'ro' => 'Editor vizual complet pentru website cu personalizare temă și constructor de pagini drag-and-drop. Personalizează culori, fonturi, layout-uri și construiește pagini personalizate cu peste 25 de tipuri de blocuri inclusiv grile de evenimente, cronometre, slidere, galerii și multe altele.',
                ],
                'short_description' => [
                    'en' => 'Theme customization & drag-and-drop page builder',
                    'ro' => 'Personalizare temă & constructor pagini drag-and-drop',
                ],
                'price' => 50.00,
                'currency' => 'EUR',
                'billing_cycle' => 'one_time',
                'pricing_model' => 'one_time',
                'is_active' => true,
                'category' => 'design',
                'icon' => 'heroicon-o-paint-brush',
                'features' => [
                    'en' => [
                        // Theme Customization
                        'Visual theme editor with live preview',
                        'Custom color schemes with primary/secondary colors',
                        'Typography customization with Google Fonts support',
                        'Header layout options (default, centered, minimal)',
                        'Footer customization with social links',
                        'Dark mode support',
                        'CSS custom overrides',

                        // Page Builder
                        'Drag-and-drop page builder',
                        '25+ block types for flexible layouts',
                        'Real-time preview in iframe',
                        'Multi-language content support (EN/RO)',

                        // Layout Blocks
                        'Hero sections with backgrounds and CTAs',
                        'Content sliders with autoplay',
                        'Spacers and dividers',

                        // Events Blocks
                        'Event grid with filtering',
                        'Event list view',
                        'Featured event highlight',
                        'Countdown timer for events',

                        // Content Blocks
                        'Rich text editor',
                        'Image with caption and lightbox',
                        'Video embed (YouTube/Vimeo)',
                        'Image gallery with masonry layout',
                        'Stats counter with animations',
                        'FAQ accordion',
                        'Interactive maps',

                        // Navigation Blocks
                        'Category navigation',
                        'Custom buttons with styles',
                        'Social media links',

                        // Marketing Blocks
                        'CTA banners',
                        'Newsletter subscription forms',
                        'Alert/promo banners',

                        // Social Proof Blocks
                        'Testimonials carousel',
                        'Partner logos grid',

                        // Advanced
                        'Custom HTML/CSS blocks',
                        'API integration for dynamic content',
                        'SEO-friendly output',
                    ],
                    'ro' => [
                        // Theme Customization
                        'Editor vizual temă cu previzualizare live',
                        'Scheme de culori personalizate cu culori primare/secundare',
                        'Personalizare tipografie cu suport Google Fonts',
                        'Opțiuni layout header (implicit, centrat, minimal)',
                        'Personalizare footer cu link-uri sociale',
                        'Suport mod întunecat',
                        'Suprascrieri CSS personalizate',

                        // Page Builder
                        'Constructor de pagini drag-and-drop',
                        'Peste 25 de tipuri de blocuri pentru layout-uri flexibile',
                        'Previzualizare în timp real în iframe',
                        'Suport conținut multi-limbă (EN/RO)',

                        // Layout Blocks
                        'Secțiuni hero cu fundaluri și CTA-uri',
                        'Slidere de conținut cu autoplay',
                        'Spații și separatoare',

                        // Events Blocks
                        'Grilă de evenimente cu filtrare',
                        'Vizualizare listă evenimente',
                        'Evidențiere eveniment principal',
                        'Cronometru invers pentru evenimente',

                        // Content Blocks
                        'Editor text îmbogățit',
                        'Imagine cu legendă și lightbox',
                        'Încorporare video (YouTube/Vimeo)',
                        'Galerie de imagini cu layout masonry',
                        'Contor statistici cu animații',
                        'Acordeon FAQ',
                        'Hărți interactive',

                        // Navigation Blocks
                        'Navigare categorii',
                        'Butoane personalizate cu stiluri',
                        'Link-uri social media',

                        // Marketing Blocks
                        'Bannere CTA',
                        'Formulare abonare newsletter',
                        'Bannere alertă/promoție',

                        // Social Proof Blocks
                        'Carusel testimoniale',
                        'Grilă logo-uri parteneri',

                        // Advanced
                        'Blocuri HTML/CSS personalizate',
                        'Integrare API pentru conținut dinamic',
                        'Output prietenos SEO',
                    ],
                ],
                'documentation_url' => '/docs/microservices/website-visual-editor',
                'metadata' => [
                    'version' => '1.0.0',
                    'author' => 'EPAS Development Team',
                    'blocks_count' => 25,
                    'theme_presets' => ['default', 'modern', 'minimal', 'bold'],
                ],
                'sort_order' => 10,
            ]
        );

        $this->command->info('✓ Website Visual Editor microservice seeded (50 EUR one-time)');
    }
}
