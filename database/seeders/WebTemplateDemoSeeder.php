<?php

namespace Database\Seeders;

use App\Enums\WebTemplateCategory;
use App\Models\WebTemplate;
use App\Models\WebTemplateCustomization;
use Illuminate\Database\Seeder;

class WebTemplateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->getTemplates();

        foreach ($templates as $data) {
            $template = WebTemplate::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            if ($template->customizations()->count() === 0) {
                $demoCustomizations = $this->getDemoCustomizations($template);
                foreach ($demoCustomizations as $customization) {
                    $template->customizations()->create($customization);
                }
            }
        }
    }

    private function getTemplates(): array
    {
        return [
            $this->organizatorClassic(),
            $this->marketplaceHub(),
            $this->agencyPro(),
            $this->teatruElegant(),
            $this->festivalVibrant(),
            $this->arenaSport(),
        ];
    }

    // ========================================================================
    // ORGANIZATOR SIMPLU
    // ========================================================================
    private function organizatorClassic(): array
    {
        return [
            'name' => 'Organizator Classic',
            'slug' => 'organizator-classic',
            'category' => WebTemplateCategory::SimpleOrganizer,
            'description' => 'Template curat și profesional pentru organizatori de evenimente simpli. Ideal pentru conferințe, workshop-uri, meetup-uri și lansări de produse.',
            'html_template_path' => 'templates/organizator-classic/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'affiliate-tracking'],
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 1,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#4F46E5', 'secondary' => '#7C3AED', 'accent' => '#F59E0B',
                'background' => '#FFFFFF', 'text' => '#1F2937', 'footer_bg' => '#111827',
            ],
            'customizable_fields' => [
                ['key' => 'logo_url', 'label' => 'Logo', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'brand_name', 'label' => 'Nume Brand', 'type' => 'text', 'default' => 'EventPro', 'group' => 'branding'],
                ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => 'Organizăm experiențe memorabile', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#4F46E5', 'group' => 'culori'],
                ['key' => 'phone', 'label' => 'Telefon', 'type' => 'text', 'default' => '+40 700 000 000', 'group' => 'contact'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'default' => 'contact@eventpro.ro', 'group' => 'contact'],
                ['key' => 'facebook_url', 'label' => 'Facebook', 'type' => 'url', 'default' => '', 'group' => 'social'],
                ['key' => 'instagram_url', 'label' => 'Instagram', 'type' => 'url', 'default' => '', 'group' => 'social'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'EventPro România',
                    'tagline' => 'Organizăm experiențe memorabile',
                    'phone' => '+40 721 234 567',
                    'email' => 'contact@eventpro.ro',
                    'address' => 'Str. Victoriei 45, București',
                ],
                'hero' => [
                    'title' => 'Descoperă Evenimente Extraordinare',
                    'subtitle' => 'Conferințe, workshop-uri și experiențe unice în România',
                    'cta_text' => 'Vezi Evenimentele',
                    'background_image' => '/demo/hero-organizer.jpg',
                ],
                'events' => [
                    [
                        'title' => 'Tech Summit 2026',
                        'date' => '+21d',
                        'time' => '09:00',
                        'venue' => 'Palatul Parlamentului, București',
                        'price_from' => 149,
                        'currency' => 'RON',
                        'image' => '/demo/event-tech.jpg',
                        'category' => 'Conferință',
                        'tickets_available' => 500,
                        'description' => 'Cea mai mare conferință de tehnologie din România',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'Early Bird', 'price' => 99, 'currency' => 'RON', 'available' => false, 'remaining' => 0, 'has_seat_selection' => false, 'description' => 'Epuizat — preț redus prima fază'],
                            ['name' => 'General Admission', 'price' => 149, 'currency' => 'RON', 'available' => true, 'remaining' => 180, 'has_seat_selection' => false, 'description' => 'Acces complet la toate sesiunile'],
                            ['name' => 'VIP', 'price' => 349, 'currency' => 'RON', 'available' => true, 'remaining' => 25, 'has_seat_selection' => false, 'description' => 'Front row, lunch inclus, meet & greet speakeri'],
                            ['name' => 'Business Pass (5 pers.)', 'price' => 599, 'currency' => 'RON', 'available' => true, 'remaining' => 10, 'has_seat_selection' => false, 'description' => 'Pachet firmă, 5 participanți, masă rezervată', 'is_group' => true],
                        ],
                    ],
                    [
                        'title' => 'Workshop Design Thinking',
                        'date' => '+35d',
                        'time' => '10:00',
                        'venue' => 'Hub Creativ, Cluj-Napoca',
                        'price_from' => 89,
                        'currency' => 'RON',
                        'image' => '/demo/event-workshop.jpg',
                        'category' => 'Workshop',
                        'tickets_available' => 30,
                        'description' => 'Învață metodologia Design Thinking de la practicienii cei mai buni',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'Participant', 'price' => 89, 'currency' => 'RON', 'available' => true, 'remaining' => 12, 'has_seat_selection' => false],
                            ['name' => 'Participant + Kit materiale', 'price' => 139, 'currency' => 'RON', 'available' => true, 'remaining' => 8, 'has_seat_selection' => false, 'description' => 'Include kit fizic de Design Thinking'],
                        ],
                    ],
                    [
                        'title' => 'Gala Premiilor Inovație',
                        'date' => '+50d',
                        'time' => '19:00',
                        'venue' => 'Ateneul Român, București',
                        'price_from' => 250,
                        'currency' => 'RON',
                        'image' => '/demo/event-gala.jpg',
                        'category' => 'Gală',
                        'tickets_available' => 200,
                        'description' => 'Celebrăm inovația și antreprenoriatul românesc',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'theater',
                            'zones' => [
                                ['name' => 'Parter Central', 'color' => '#7C3AED', 'rows' => 8, 'seats_per_row' => 20, 'available' => 85],
                                ['name' => 'Parter Lateral', 'color' => '#6366F1', 'rows' => 6, 'seats_per_row' => 12, 'available' => 45],
                                ['name' => 'Balcon', 'color' => '#8B5CF6', 'rows' => 4, 'seats_per_row' => 25, 'available' => 70],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Parter Central', 'price' => 400, 'currency' => 'RON', 'available' => true, 'remaining' => 40, 'has_seat_selection' => true, 'seat_zone' => 'parter-central', 'description' => 'Rândurile 1-8, vedere perfectă'],
                            ['name' => 'Parter Lateral', 'price' => 300, 'currency' => 'RON', 'available' => true, 'remaining' => 25, 'has_seat_selection' => true, 'seat_zone' => 'parter-lateral'],
                            ['name' => 'Balcon', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 55, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                            ['name' => 'Lojă VIP (6 pers.)', 'price' => 2400, 'currency' => 'RON', 'available' => true, 'remaining' => 2, 'has_seat_selection' => true, 'seat_zone' => 'loja', 'is_group' => true, 'description' => 'Lojă privată, champagne, catering'],
                        ],
                    ],
                    [
                        'title' => 'Meetup Startup Community',
                        'date' => '+10d',
                        'time' => '18:30',
                        'venue' => 'Impact Hub, Timișoara',
                        'price_from' => 0,
                        'currency' => 'RON',
                        'image' => '/demo/event-meetup.jpg',
                        'category' => 'Meetup',
                        'tickets_available' => 80,
                        'description' => 'Networking și prezentări din ecosistemul startup',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'Acces Gratuit', 'price' => 0, 'currency' => 'RON', 'available' => true, 'remaining' => 22, 'has_seat_selection' => false],
                            ['name' => 'Supporter (donație)', 'price' => 25, 'currency' => 'RON', 'available' => true, 'remaining' => 50, 'has_seat_selection' => false, 'description' => 'Include tricou comunitate'],
                        ],
                    ],
                ],
                'stats' => [
                    'events_organized' => 150,
                    'total_attendees' => 25000,
                    'cities' => 12,
                    'satisfaction_rate' => 98,
                ],
                'testimonials' => [
                    ['name' => 'Maria Popescu', 'role' => 'CEO, TechStart', 'text' => 'Organizare impecabilă! Conferința a fost un real succes.', 'avatar' => '/demo/avatar-1.jpg'],
                    ['name' => 'Andrei Ionescu', 'role' => 'Manager, Creative Hub', 'text' => 'Procesul de ticketing a fost fluid, participanții au fost foarte mulțumiți.', 'avatar' => '/demo/avatar-2.jpg'],
                ],
            ],
        ];
    }

    // ========================================================================
    // MARKETPLACE
    // ========================================================================
    private function marketplaceHub(): array
    {
        return [
            'name' => 'Marketplace Hub',
            'slug' => 'marketplace-hub',
            'category' => WebTemplateCategory::Marketplace,
            'description' => 'Template complet pentru marketplace de bilete cu listing-uri multiple, filtre avansate, categorii și pagini de organizatori.',
            'html_template_path' => 'templates/marketplace-hub/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'shop', 'affiliate-tracking', 'efactura', 'accounting'],
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 2,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#DC2626', 'secondary' => '#1E40AF', 'accent' => '#F59E0B',
                'background' => '#F9FAFB', 'text' => '#111827', 'footer_bg' => '#1F2937',
            ],
            'customizable_fields' => [
                ['key' => 'logo_url', 'label' => 'Logo Marketplace', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'marketplace_name', 'label' => 'Nume Marketplace', 'type' => 'text', 'default' => 'Tixello', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#DC2626', 'group' => 'culori'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'Tixello Marketplace',
                    'tagline' => 'Toate evenimentele. Un singur loc.',
                    'phone' => '+40 31 000 1234',
                    'email' => 'info@tixello.ro',
                ],
                'hero' => [
                    'title' => 'Găsește Evenimentul Perfect',
                    'subtitle' => 'Concerte, teatru, festivaluri, sport — peste 5.000 de evenimente',
                    'search_placeholder' => 'Caută evenimente, artiști, locații...',
                ],
                'featured_events' => [
                    [
                        'title' => 'Untold Festival',
                        'date' => '+4m',
                        'venue' => 'Cluj Arena, Cluj-Napoca',
                        'price_from' => 399,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-untold.jpg',
                        'category' => 'Festival',
                        'organizer' => 'Untold Universe',
                        'badge' => 'Sold Out Soon',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'General Access 4 zile', 'price' => 399, 'currency' => 'RON', 'available' => true, 'remaining' => 2200, 'has_seat_selection' => false],
                            ['name' => 'VIP 4 zile', 'price' => 799, 'currency' => 'RON', 'available' => true, 'remaining' => 180, 'has_seat_selection' => false, 'description' => 'VIP deck, fast lane, lounge'],
                            ['name' => 'Single Day', 'price' => 199, 'currency' => 'RON', 'available' => true, 'remaining' => 5000, 'has_seat_selection' => false],
                        ],
                    ],
                    [
                        'title' => 'André Rieu — Concert Extraordinar',
                        'date' => '+2m',
                        'venue' => 'Sala Palatului, București',
                        'price_from' => 350,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-concert.jpg',
                        'category' => 'Concert',
                        'organizer' => 'Events Live',
                        'badge' => 'Top Seller',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'concert-hall',
                            'zones' => [
                                ['name' => 'Parter VIP', 'color' => '#DC2626', 'rows' => 5, 'seats_per_row' => 30, 'available' => 20],
                                ['name' => 'Parter', 'color' => '#F59E0B', 'rows' => 15, 'seats_per_row' => 30, 'available' => 120],
                                ['name' => 'Balcon I', 'color' => '#3B82F6', 'rows' => 5, 'seats_per_row' => 40, 'available' => 85],
                                ['name' => 'Balcon II', 'color' => '#8B5CF6', 'rows' => 5, 'seats_per_row' => 40, 'available' => 140],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Parter VIP (rândurile 1-5)', 'price' => 750, 'currency' => 'RON', 'available' => true, 'remaining' => 20, 'has_seat_selection' => true, 'seat_zone' => 'parter-vip'],
                            ['name' => 'Parter (rândurile 6-20)', 'price' => 450, 'currency' => 'RON', 'available' => true, 'remaining' => 120, 'has_seat_selection' => true, 'seat_zone' => 'parter'],
                            ['name' => 'Balcon I', 'price' => 350, 'currency' => 'RON', 'available' => true, 'remaining' => 85, 'has_seat_selection' => true, 'seat_zone' => 'balcon-1'],
                            ['name' => 'Balcon II', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 140, 'has_seat_selection' => true, 'seat_zone' => 'balcon-2'],
                        ],
                    ],
                    [
                        'title' => 'Hamlet — Teatrul Național',
                        'date' => '+18d',
                        'venue' => 'Teatrul Național, București',
                        'price_from' => 60,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-theater.jpg',
                        'category' => 'Teatru',
                        'organizer' => 'TNB',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'theater',
                            'zones' => [
                                ['name' => 'Cat. I', 'color' => '#B91C1C', 'rows' => 5, 'seats_per_row' => 24, 'available' => 15],
                                ['name' => 'Cat. II', 'color' => '#D97706', 'rows' => 8, 'seats_per_row' => 24, 'available' => 60],
                                ['name' => 'Cat. III', 'color' => '#059669', 'rows' => 7, 'seats_per_row' => 24, 'available' => 90],
                                ['name' => 'Balcon', 'color' => '#6366F1', 'rows' => 4, 'seats_per_row' => 30, 'available' => 70],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Categoria I', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 15, 'has_seat_selection' => true, 'seat_zone' => 'cat-1'],
                            ['name' => 'Categoria II', 'price' => 80, 'currency' => 'RON', 'available' => true, 'remaining' => 60, 'has_seat_selection' => true, 'seat_zone' => 'cat-2'],
                            ['name' => 'Categoria III', 'price' => 60, 'currency' => 'RON', 'available' => true, 'remaining' => 90, 'has_seat_selection' => true, 'seat_zone' => 'cat-3'],
                            ['name' => 'Balcon', 'price' => 50, 'currency' => 'RON', 'available' => true, 'remaining' => 70, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                        ],
                    ],
                    [
                        'title' => 'CFR Cluj vs FCSB — Superliga',
                        'date' => '+12d',
                        'venue' => 'Stadion Dr. Constantin Rădulescu',
                        'price_from' => 35,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-sport.jpg',
                        'category' => 'Sport',
                        'organizer' => 'CFR Cluj',
                        'badge' => 'Derby',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'stadium',
                            'zones' => [
                                ['name' => 'Tribuna I (VIP)', 'color' => '#DC2626', 'capacity' => 2000, 'available' => 150],
                                ['name' => 'Tribuna II', 'color' => '#F59E0B', 'capacity' => 4000, 'available' => 800],
                                ['name' => 'Peluza Nord', 'color' => '#10B981', 'capacity' => 5000, 'available' => 1200],
                                ['name' => 'Peluza Sud', 'color' => '#3B82F6', 'capacity' => 5000, 'available' => 2000],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Tribuna I (VIP)', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 150, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-1-vip', 'description' => 'Loc numerotat, acces lounge'],
                            ['name' => 'Tribuna II', 'price' => 65, 'currency' => 'RON', 'available' => true, 'remaining' => 800, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2'],
                            ['name' => 'Peluza Nord (gazdă)', 'price' => 35, 'currency' => 'RON', 'available' => true, 'remaining' => 1200, 'has_seat_selection' => false, 'seat_zone' => 'peluza-nord'],
                            ['name' => 'Peluza Sud (oaspeți)', 'price' => 35, 'currency' => 'RON', 'available' => true, 'remaining' => 2000, 'has_seat_selection' => false, 'seat_zone' => 'peluza-sud'],
                        ],
                    ],
                    [
                        'title' => 'Electric Castle',
                        'date' => '+3m',
                        'venue' => 'Castelul Banffy, Bonțida',
                        'price_from' => 499,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-ec.jpg',
                        'category' => 'Festival',
                        'organizer' => 'Electric Castle',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'General 4 zile + Camping', 'price' => 499, 'currency' => 'RON', 'available' => true, 'remaining' => 3500, 'has_seat_selection' => false],
                            ['name' => 'VIP 4 zile', 'price' => 999, 'currency' => 'RON', 'available' => true, 'remaining' => 200, 'has_seat_selection' => false, 'description' => 'VIP stage view, lounge, glamping'],
                            ['name' => 'Single Day Sâmbătă', 'price' => 249, 'currency' => 'RON', 'available' => true, 'remaining' => 1500, 'has_seat_selection' => false],
                        ],
                    ],
                    [
                        'title' => 'Stand-up cu Badea, Bordea & Micutzu',
                        'date' => '+25d',
                        'venue' => 'Filarmonica Brașov',
                        'price_from' => 80,
                        'currency' => 'RON',
                        'image' => '/demo/marketplace-standup.jpg',
                        'category' => 'Stand-up',
                        'organizer' => 'Comedy Shows',
                        'badge' => 'Aproape Sold Out',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'theater',
                            'zones' => [
                                ['name' => 'Parter', 'color' => '#DC2626', 'rows' => 12, 'seats_per_row' => 20, 'available' => 18],
                                ['name' => 'Balcon', 'color' => '#6366F1', 'rows' => 5, 'seats_per_row' => 25, 'available' => 30],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Parter', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 18, 'has_seat_selection' => true, 'seat_zone' => 'parter'],
                            ['name' => 'Balcon', 'price' => 80, 'currency' => 'RON', 'available' => true, 'remaining' => 30, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                        ],
                    ],
                ],
                'categories' => [
                    ['name' => 'Concerte', 'icon' => 'musical-note', 'count' => 1250],
                    ['name' => 'Teatru & Operă', 'icon' => 'building-library', 'count' => 480],
                    ['name' => 'Festivaluri', 'icon' => 'fire', 'count' => 85],
                    ['name' => 'Sport', 'icon' => 'trophy', 'count' => 320],
                    ['name' => 'Stand-up', 'icon' => 'face-smile', 'count' => 210],
                    ['name' => 'Copii', 'icon' => 'heart', 'count' => 150],
                    ['name' => 'Conferințe', 'icon' => 'academic-cap', 'count' => 95],
                    ['name' => 'Expoziții', 'icon' => 'photo', 'count' => 60],
                ],
                'cities' => ['București', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Brașov', 'Constanța', 'Sibiu', 'Oradea'],
                'stats' => [
                    'total_events' => 5200,
                    'organizers' => 340,
                    'tickets_sold' => 1500000,
                    'cities' => 45,
                ],
            ],
        ];
    }

    // ========================================================================
    // AGENȚIE ARTIȘTI
    // ========================================================================
    private function agencyPro(): array
    {
        return [
            'name' => 'Agency Pro',
            'slug' => 'agency-pro',
            'category' => WebTemplateCategory::ArtistAgency,
            'description' => 'Template premium pentru agenții de artiști. Showcase artiști, calendar turnee, rider tehnic, booking și galerii media.',
            'html_template_path' => 'templates/agency-pro/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'efactura', 'accounting'],
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 3,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#0F172A', 'secondary' => '#6366F1', 'accent' => '#F43F5E',
                'background' => '#FFFFFF', 'text' => '#1E293B', 'footer_bg' => '#0F172A',
            ],
            'customizable_fields' => [
                ['key' => 'agency_name', 'label' => 'Nume Agenție', 'type' => 'text', 'default' => 'ArtistConnect', 'group' => 'branding'],
                ['key' => 'logo_url', 'label' => 'Logo', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#0F172A', 'group' => 'culori'],
                ['key' => 'accent_color', 'label' => 'Culoare Accent', 'type' => 'color', 'default' => '#F43F5E', 'group' => 'culori'],
                ['key' => 'booking_email', 'label' => 'Email Booking', 'type' => 'text', 'default' => 'booking@agentie.ro', 'group' => 'contact'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'ArtistConnect Agency',
                    'tagline' => 'Managementul artiștilor tăi preferați',
                    'phone' => '+40 722 345 678',
                    'email' => 'booking@artistconnect.ro',
                    'address' => 'Str. Franceză 18, București',
                ],
                'hero' => [
                    'title' => 'Artiștii Care Definesc Generația',
                    'subtitle' => 'Management, booking și producție pentru cei mai buni artiști din România',
                    'cta_text' => 'Descoperă Artiștii',
                ],
                'artists' => [
                    [
                        'name' => 'Elena Marinescu',
                        'genre' => 'Pop / Electronic',
                        'image' => '/demo/artist-elena.jpg',
                        'bio' => 'Voce unică și prezență scenică electrizantă. 3 albume, peste 50M streams.',
                        'social' => ['instagram' => '350K', 'spotify' => '1.2M', 'youtube' => '800K'],
                        'upcoming_shows' => 5,
                        'available_for_booking' => true,
                    ],
                    [
                        'name' => 'Radu & The Band',
                        'genre' => 'Rock / Alternative',
                        'image' => '/demo/artist-radu.jpg',
                        'bio' => 'Rock alternativ cu influențe folk. Headlineri la Electric Castle.',
                        'social' => ['instagram' => '120K', 'spotify' => '500K', 'youtube' => '200K'],
                        'upcoming_shows' => 8,
                        'available_for_booking' => true,
                    ],
                    [
                        'name' => 'DJ Matei',
                        'genre' => 'House / Techno',
                        'image' => '/demo/artist-dj.jpg',
                        'bio' => 'Resident DJ la Sunwaves. Turnee internaționale în 15 țări.',
                        'social' => ['instagram' => '85K', 'spotify' => '300K', 'soundcloud' => '150K'],
                        'upcoming_shows' => 12,
                        'available_for_booking' => true,
                    ],
                    [
                        'name' => 'Ana Vocal',
                        'genre' => 'Jazz / Soul',
                        'image' => '/demo/artist-ana.jpg',
                        'bio' => 'Cea mai premiată voce de jazz din România.',
                        'social' => ['instagram' => '45K', 'spotify' => '180K'],
                        'upcoming_shows' => 3,
                        'available_for_booking' => false,
                    ],
                ],
                'upcoming_events' => [
                    [
                        'title' => 'Elena Marinescu — Summer Tour',
                        'date' => '+45d',
                        'venue' => 'Arenele Romane, București',
                        'price_from' => 120,
                        'currency' => 'RON',
                        'category' => 'Concert',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'Gazon', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 800, 'has_seat_selection' => false],
                            ['name' => 'Golden Circle', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 100, 'has_seat_selection' => false, 'description' => 'Primele 5 rânduri din fața scenei'],
                            ['name' => 'VIP Backstage', 'price' => 500, 'currency' => 'RON', 'available' => true, 'remaining' => 15, 'has_seat_selection' => false, 'description' => 'Meet & greet, foto, acces backstage'],
                        ],
                    ],
                    [
                        'title' => 'Radu & The Band — Rock Night',
                        'date' => '+20d',
                        'venue' => 'Quantic Club, București',
                        'price_from' => 60,
                        'currency' => 'RON',
                        'category' => 'Concert',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'General Admission', 'price' => 60, 'currency' => 'RON', 'available' => true, 'remaining' => 150, 'has_seat_selection' => false],
                            ['name' => 'Fan Pack', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 30, 'has_seat_selection' => false, 'description' => 'Bilet + tricou + poster semnat'],
                        ],
                    ],
                    [
                        'title' => 'DJ Matei @ Sunwaves',
                        'date' => '+3m',
                        'venue' => 'Mamaia Nord',
                        'price_from' => 200,
                        'currency' => 'RON',
                        'category' => 'Festival',
                        'has_seating_map' => false,
                        'ticket_types' => [
                            ['name' => 'General Pass', 'price' => 200, 'currency' => 'RON', 'available' => true, 'remaining' => 2000, 'has_seat_selection' => false],
                            ['name' => 'VIP', 'price' => 450, 'currency' => 'RON', 'available' => true, 'remaining' => 150, 'has_seat_selection' => false],
                        ],
                    ],
                ],
                'stats' => [
                    'artists' => 28,
                    'events_yearly' => 350,
                    'countries' => 15,
                    'streams_total' => '50M+',
                ],
            ],
        ];
    }

    // ========================================================================
    // TEATRU
    // ========================================================================
    private function teatruElegant(): array
    {
        return [
            'name' => 'Teatru Elegant',
            'slug' => 'teatru-elegant',
            'category' => WebTemplateCategory::Theater,
            'description' => 'Template sofisticat pentru teatre, opere și filarmonici. Repertoriu, stagiune, echipă artistică, harta sălii cu locuri numerotate.',
            'html_template_path' => 'templates/teatru-elegant/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'door-sales', 'ticket-customizer', 'efactura'],
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 4,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#7C2D12', 'secondary' => '#B45309', 'accent' => '#D4AF37',
                'background' => '#FDF8F0', 'text' => '#292524', 'footer_bg' => '#1C1917',
            ],
            'customizable_fields' => [
                ['key' => 'theater_name', 'label' => 'Nume Teatru', 'type' => 'text', 'default' => 'Teatrul Național', 'group' => 'branding'],
                ['key' => 'logo_url', 'label' => 'Logo/Stemă', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#7C2D12', 'group' => 'culori'],
                ['key' => 'gold_accent', 'label' => 'Accent Auriu', 'type' => 'color', 'default' => '#D4AF37', 'group' => 'culori'],
                ['key' => 'season_name', 'label' => 'Stagiune', 'type' => 'text', 'default' => 'Stagiunea 2025-2026', 'group' => 'conținut'],
                ['key' => 'box_office_phone', 'label' => 'Telefon Casă Bilete', 'type' => 'text', 'default' => '+40 21 000 0000', 'group' => 'contact'],
                ['key' => 'has_seating_map', 'label' => 'Hartă Locuri', 'type' => 'toggle', 'default' => 'true', 'group' => 'funcționalități'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'Teatrul Național „I. L. Caragiale"',
                    'tagline' => 'Stagiunea 2025-2026',
                    'phone' => '+40 21 314 71 71',
                    'email' => 'secretariat@tnb.ro',
                    'address' => 'Bd. Nicolae Bălcescu 2, București',
                ],
                'hero' => [
                    'title' => 'Bine ați venit la Teatrul Național',
                    'subtitle' => 'Stagiunea 2025-2026 — 24 premiere, peste 200 de spectacole',
                    'background_image' => '/demo/hero-theater.jpg',
                ],
                'repertoire' => [
                    [
                        'title' => 'Hamlet',
                        'author' => 'William Shakespeare',
                        'director' => 'Silviu Purcărete',
                        'image' => '/demo/theater-hamlet.jpg',
                        'duration' => '2h 45min',
                        'hall' => 'Sala Mare',
                        'age_rating' => '14+',
                        'next_show' => '+14d 19:00',
                        'price_from' => 60,
                        'description' => 'O viziune contemporană asupra capodoperei shakespeariene',
                        'cast' => ['Marcel Iureș', 'Maia Morgenstern', 'Victor Rebengiuc'],
                        'category' => 'Dramă',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'theater-classic',
                            'hall_name' => 'Sala Mare',
                            'total_capacity' => 620,
                            'zones' => [
                                ['name' => 'Categoria I (rând 1-5)', 'color' => '#B91C1C', 'rows' => 5, 'seats_per_row' => 24, 'available' => 18, 'price' => 120],
                                ['name' => 'Categoria II (rând 6-12)', 'color' => '#D97706', 'rows' => 7, 'seats_per_row' => 24, 'available' => 55, 'price' => 80],
                                ['name' => 'Categoria III (rând 13-20)', 'color' => '#059669', 'rows' => 8, 'seats_per_row' => 24, 'available' => 95, 'price' => 60],
                                ['name' => 'Balcon', 'color' => '#6366F1', 'rows' => 4, 'seats_per_row' => 25, 'available' => 50, 'price' => 50],
                                ['name' => 'Lojă (4 pers.)', 'color' => '#D4AF37', 'rows' => 1, 'seats_per_row' => 6, 'available' => 2, 'price' => 400, 'is_group' => true],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Categoria I', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 18, 'has_seat_selection' => true, 'seat_zone' => 'cat-1', 'description' => 'Rândurile 1-5, vizibilitate excelentă'],
                            ['name' => 'Categoria II', 'price' => 80, 'currency' => 'RON', 'available' => true, 'remaining' => 55, 'has_seat_selection' => true, 'seat_zone' => 'cat-2', 'description' => 'Rândurile 6-12'],
                            ['name' => 'Categoria III', 'price' => 60, 'currency' => 'RON', 'available' => true, 'remaining' => 95, 'has_seat_selection' => true, 'seat_zone' => 'cat-3', 'description' => 'Rândurile 13-20'],
                            ['name' => 'Balcon', 'price' => 50, 'currency' => 'RON', 'available' => true, 'remaining' => 50, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                            ['name' => 'Lojă (4 locuri)', 'price' => 400, 'currency' => 'RON', 'available' => true, 'remaining' => 2, 'has_seat_selection' => true, 'seat_zone' => 'loja', 'is_group' => true, 'description' => 'Lojă privată, servire inclusă'],
                        ],
                    ],
                    [
                        'title' => 'O scrisoare pierdută',
                        'author' => 'I. L. Caragiale',
                        'director' => 'Alexandru Dabija',
                        'image' => '/demo/theater-scrisoare.jpg',
                        'duration' => '2h 15min',
                        'hall' => 'Sala Mare',
                        'age_rating' => '12+',
                        'next_show' => '+21d 19:00',
                        'price_from' => 50,
                        'description' => 'Comedia politică românească în toată splendoarea ei',
                        'cast' => ['Horaţiu Mălăele', 'Mircea Diaconu'],
                        'category' => 'Comedie',
                        'has_seating_map' => true,
                        'ticket_types' => [
                            ['name' => 'Categoria I', 'price' => 100, 'currency' => 'RON', 'available' => true, 'remaining' => 30, 'has_seat_selection' => true, 'seat_zone' => 'cat-1'],
                            ['name' => 'Categoria II', 'price' => 70, 'currency' => 'RON', 'available' => true, 'remaining' => 65, 'has_seat_selection' => true, 'seat_zone' => 'cat-2'],
                            ['name' => 'Categoria III', 'price' => 50, 'currency' => 'RON', 'available' => true, 'remaining' => 100, 'has_seat_selection' => true, 'seat_zone' => 'cat-3'],
                            ['name' => 'Balcon', 'price' => 40, 'currency' => 'RON', 'available' => true, 'remaining' => 60, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                        ],
                    ],
                    [
                        'title' => 'Așteptându-l pe Godot',
                        'author' => 'Samuel Beckett',
                        'director' => 'Radu Afrim',
                        'image' => '/demo/theater-godot.jpg',
                        'duration' => '2h',
                        'hall' => 'Sala Studio',
                        'age_rating' => '16+',
                        'next_show' => '+28d 20:00',
                        'price_from' => 45,
                        'description' => 'Absurdul existenței într-o montare de excepție',
                        'cast' => ['Dan Puric', 'Florin Piersic Jr.'],
                        'category' => 'Dramă',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'studio',
                            'hall_name' => 'Sala Studio',
                            'total_capacity' => 150,
                            'zones' => [
                                ['name' => 'Rândurile 1-5', 'color' => '#B91C1C', 'rows' => 5, 'seats_per_row' => 15, 'available' => 12, 'price' => 70],
                                ['name' => 'Rândurile 6-10', 'color' => '#D97706', 'rows' => 5, 'seats_per_row' => 15, 'available' => 28, 'price' => 45],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Rândurile 1-5', 'price' => 70, 'currency' => 'RON', 'available' => true, 'remaining' => 12, 'has_seat_selection' => true, 'seat_zone' => 'front'],
                            ['name' => 'Rândurile 6-10', 'price' => 45, 'currency' => 'RON', 'available' => true, 'remaining' => 28, 'has_seat_selection' => true, 'seat_zone' => 'back'],
                        ],
                    ],
                    [
                        'title' => 'Lacul Lebedelor — Balet',
                        'author' => 'Piotr Ilici Ceaikovski',
                        'director' => 'Corina Dumitrescu',
                        'image' => '/demo/theater-balet.jpg',
                        'duration' => '2h 30min',
                        'hall' => 'Sala Mare',
                        'age_rating' => 'Toate vârstele',
                        'next_show' => '+40d 18:00',
                        'price_from' => 80,
                        'description' => 'Capodopera baletului clasic într-o producție magică',
                        'cast' => ['Alina Cojocaru'],
                        'category' => 'Balet',
                        'has_seating_map' => true,
                        'ticket_types' => [
                            ['name' => 'Categoria I', 'price' => 150, 'currency' => 'RON', 'available' => true, 'remaining' => 35, 'has_seat_selection' => true, 'seat_zone' => 'cat-1'],
                            ['name' => 'Categoria II', 'price' => 110, 'currency' => 'RON', 'available' => true, 'remaining' => 70, 'has_seat_selection' => true, 'seat_zone' => 'cat-2'],
                            ['name' => 'Categoria III', 'price' => 80, 'currency' => 'RON', 'available' => true, 'remaining' => 90, 'has_seat_selection' => true, 'seat_zone' => 'cat-3'],
                            ['name' => 'Balcon', 'price' => 65, 'currency' => 'RON', 'available' => true, 'remaining' => 45, 'has_seat_selection' => true, 'seat_zone' => 'balcon'],
                            ['name' => 'Lojă (4 locuri)', 'price' => 500, 'currency' => 'RON', 'available' => true, 'remaining' => 3, 'has_seat_selection' => true, 'seat_zone' => 'loja', 'is_group' => true],
                        ],
                    ],
                ],
                'halls' => [
                    ['name' => 'Sala Mare', 'capacity' => 620, 'has_seating_map' => true],
                    ['name' => 'Sala Studio', 'capacity' => 150, 'has_seating_map' => true],
                    ['name' => 'Sala Atelier', 'capacity' => 80, 'has_seating_map' => false],
                ],
                'team' => [
                    ['name' => 'Ion Caramitru', 'role' => 'Director General', 'image' => '/demo/team-director.jpg'],
                    ['name' => 'Silviu Purcărete', 'role' => 'Director Artistic', 'image' => '/demo/team-artistic.jpg'],
                ],
                'stats' => [
                    'shows_per_season' => 200,
                    'premieres' => 24,
                    'artists' => 120,
                    'years_of_history' => 172,
                ],
            ],
        ];
    }

    // ========================================================================
    // FESTIVAL
    // ========================================================================
    private function festivalVibrant(): array
    {
        return [
            'name' => 'Festival Vibrant',
            'slug' => 'festival-vibrant',
            'category' => WebTemplateCategory::Festival,
            'description' => 'Template dinamic pentru festivaluri muzicale și culturale. Line-up, scene multiple, program pe zile, experiențe, camping.',
            'html_template_path' => 'templates/festival-vibrant/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'shop', 'door-sales', 'affiliate-tracking', 'efactura'],
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 5,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#7C3AED', 'secondary' => '#EC4899', 'accent' => '#10B981',
                'background' => '#0F0720', 'text' => '#FFFFFF', 'footer_bg' => '#0A0514',
            ],
            'customizable_fields' => [
                ['key' => 'festival_name', 'label' => 'Nume Festival', 'type' => 'text', 'default' => 'SonicWave Festival', 'group' => 'branding'],
                ['key' => 'logo_url', 'label' => 'Logo Festival', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'edition', 'label' => 'Ediție', 'type' => 'text', 'default' => 'Ediția a VII-a', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#7C3AED', 'group' => 'culori'],
                ['key' => 'gradient_from', 'label' => 'Gradient Start', 'type' => 'color', 'default' => '#7C3AED', 'group' => 'culori'],
                ['key' => 'gradient_to', 'label' => 'Gradient End', 'type' => 'color', 'default' => '#EC4899', 'group' => 'culori'],
                ['key' => 'dates', 'label' => 'Date Festival', 'type' => 'text', 'default' => '17-20 Iulie 2026', 'group' => 'conținut'],
                ['key' => 'location', 'label' => 'Locație', 'type' => 'text', 'default' => 'Cluj-Napoca', 'group' => 'conținut'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'SonicWave Festival',
                    'tagline' => 'Feel The Frequency',
                    'dates' => '17-20 Iulie 2026',
                    'location' => 'Parcul Central, Cluj-Napoca',
                    'email' => 'info@sonicwave.ro',
                ],
                'hero' => [
                    'title' => 'SONICWAVE',
                    'subtitle' => 'Feel The Frequency · 17-20 Iulie · Cluj-Napoca',
                    'countdown_to' => '+4m',
                    'video_background' => '/demo/festival-hero.mp4',
                ],
                'lineup' => [
                    'headliners' => [
                        ['name' => 'Disclosure', 'image' => '/demo/artist-disclosure.jpg', 'day' => 'Vineri', 'stage' => 'Main Stage'],
                        ['name' => 'Amelie Lens', 'image' => '/demo/artist-amelie.jpg', 'day' => 'Sâmbătă', 'stage' => 'Main Stage'],
                        ['name' => 'Bicep', 'image' => '/demo/artist-bicep.jpg', 'day' => 'Duminică', 'stage' => 'Main Stage'],
                    ],
                    'day_1' => [
                        ['name' => 'Disclosure', 'time' => '23:00', 'stage' => 'Main Stage'],
                        ['name' => 'Bonobo', 'time' => '21:00', 'stage' => 'Main Stage'],
                        ['name' => 'Blond:ish', 'time' => '19:00', 'stage' => 'Sunset Stage'],
                        ['name' => 'Local Artists', 'time' => '17:00', 'stage' => 'Discovery Stage'],
                    ],
                    'day_2' => [
                        ['name' => 'Amelie Lens', 'time' => '23:30', 'stage' => 'Main Stage'],
                        ['name' => 'RÜFÜS DU SOL', 'time' => '21:00', 'stage' => 'Main Stage'],
                        ['name' => 'Peggy Gou', 'time' => '19:30', 'stage' => 'Sunset Stage'],
                        ['name' => 'DJ Matei', 'time' => '17:00', 'stage' => 'Discovery Stage'],
                    ],
                    'day_3' => [
                        ['name' => 'Bicep', 'time' => '23:00', 'stage' => 'Main Stage'],
                        ['name' => 'Four Tet', 'time' => '21:00', 'stage' => 'Main Stage'],
                        ['name' => 'Fatima Yamaha', 'time' => '19:00', 'stage' => 'Sunset Stage'],
                    ],
                ],
                'stages' => [
                    ['name' => 'Main Stage', 'capacity' => 15000, 'genre' => 'Electronic / Dance'],
                    ['name' => 'Sunset Stage', 'capacity' => 5000, 'genre' => 'Deep House / Melodic'],
                    ['name' => 'Discovery Stage', 'capacity' => 2000, 'genre' => 'Underground / Local'],
                ],
                'tickets' => [
                    ['type' => 'General Access — 4 Zile', 'price' => 499, 'currency' => 'RON', 'available' => true, 'remaining' => 3500, 'perks' => ['Acces toate scenele', 'Camping standard'], 'has_seat_selection' => false],
                    ['type' => 'VIP Pass — 4 Zile', 'price' => 899, 'currency' => 'RON', 'available' => true, 'remaining' => 180, 'perks' => ['Acces VIP viewing deck', 'Fast lane entry', 'VIP Lounge', 'Parking gratuit'], 'has_seat_selection' => false],
                    ['type' => 'Backstage Experience', 'price' => 1999, 'currency' => 'RON', 'available' => true, 'remaining' => 10, 'perks' => ['Tot ce include VIP', 'Backstage tour zilnic', 'Meet & greet artiști', 'Open bar'], 'has_seat_selection' => false],
                    ['type' => 'Single Day — Vineri', 'price' => 199, 'currency' => 'RON', 'available' => true, 'remaining' => 1500, 'perks' => ['Acces toate scenele'], 'has_seat_selection' => false],
                    ['type' => 'Single Day — Sâmbătă', 'price' => 249, 'currency' => 'RON', 'available' => true, 'remaining' => 800, 'perks' => ['Acces toate scenele'], 'has_seat_selection' => false],
                    ['type' => 'Camping Upgrade — Glamping', 'price' => 350, 'currency' => 'RON', 'available' => true, 'remaining' => 50, 'perks' => ['Cort premium montat', 'Saltea și lenjerie', 'Acces dușuri VIP'], 'has_seat_selection' => false],
                ],
                'experiences' => [
                    ['name' => 'Food Court', 'description' => '30+ food vendors din toată România', 'icon' => 'utensils'],
                    ['name' => 'Art Zone', 'description' => 'Instalații interactive și live painting', 'icon' => 'palette'],
                    ['name' => 'Wellness Area', 'description' => 'Yoga, meditație și masaj', 'icon' => 'heart'],
                    ['name' => 'Camping', 'description' => 'Camping standard și glamping', 'icon' => 'tent'],
                ],
                'info' => [
                    'gates_open' => '14:00',
                    'minimum_age' => 16,
                    'camping_available' => true,
                    'parking_available' => true,
                    'cashless_payment' => true,
                ],
                'stats' => [
                    'editions' => 6,
                    'attendees_last_year' => 35000,
                    'artists' => 60,
                    'stages' => 3,
                ],
            ],
        ];
    }

    // ========================================================================
    // STADION
    // ========================================================================
    private function arenaSport(): array
    {
        return [
            'name' => 'Arena Sport',
            'slug' => 'arena-sport',
            'category' => WebTemplateCategory::Stadium,
            'description' => 'Template pentru stadioane și arene. Evenimente sportive, concerte, hartă sectoare, abonamente sezon, info transport.',
            'html_template_path' => 'templates/arena-sport/index.html',
            'tech_stack' => ['HTML', 'Alpine.js', 'Tailwind CSS'],
            'compatible_microservices' => ['analytics', 'crm', 'shop', 'door-sales', 'efactura'],
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 6,
            'version' => '1.0.0',
            'color_scheme' => [
                'primary' => '#1D4ED8', 'secondary' => '#059669', 'accent' => '#EF4444',
                'background' => '#F8FAFC', 'text' => '#0F172A', 'footer_bg' => '#0F172A',
            ],
            'customizable_fields' => [
                ['key' => 'arena_name', 'label' => 'Nume Arenă', 'type' => 'text', 'default' => 'Arena Națională', 'group' => 'branding'],
                ['key' => 'logo_url', 'label' => 'Logo', 'type' => 'image', 'default' => '', 'group' => 'branding'],
                ['key' => 'primary_color', 'label' => 'Culoare Principală', 'type' => 'color', 'default' => '#1D4ED8', 'group' => 'culori'],
                ['key' => 'team_color', 'label' => 'Culoare Echipă', 'type' => 'color', 'default' => '#EF4444', 'group' => 'culori'],
                ['key' => 'has_season_tickets', 'label' => 'Abonamente Sezon', 'type' => 'toggle', 'default' => 'true', 'group' => 'funcționalități'],
                ['key' => 'has_sector_map', 'label' => 'Hartă Sectoare', 'type' => 'toggle', 'default' => 'true', 'group' => 'funcționalități'],
            ],
            'default_demo_data' => [
                'site' => [
                    'name' => 'Arena Națională București',
                    'tagline' => 'Cel mai mare stadion din România',
                    'phone' => '+40 21 000 5555',
                    'email' => 'bilete@arena-nationala.ro',
                    'address' => 'Bd. Basarabia 37-39, București',
                ],
                'hero' => [
                    'title' => 'Arena Națională',
                    'subtitle' => 'Emoții la superlativ — sport, muzică, spectacol',
                    'background_image' => '/demo/hero-arena.jpg',
                    'capacity' => '55.634 locuri',
                ],
                'upcoming_events' => [
                    [
                        'title' => 'România vs Italia — UEFA Nations League',
                        'date' => '+30d',
                        'time' => '21:45',
                        'type' => 'Fotbal',
                        'image' => '/demo/arena-football.jpg',
                        'price_from' => 50,
                        'currency' => 'RON',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'stadium-full',
                            'total_capacity' => 55634,
                            'zones' => [
                                ['name' => 'Tribuna Oficială (VIP)', 'color' => '#DC2626', 'capacity' => 3000, 'available' => 200, 'price' => 250],
                                ['name' => 'Tribuna I', 'color' => '#F59E0B', 'capacity' => 8000, 'available' => 1500, 'price' => 120],
                                ['name' => 'Tribuna II', 'color' => '#3B82F6', 'capacity' => 10000, 'available' => 3000, 'price' => 80],
                                ['name' => 'Peluza Nord', 'color' => '#10B981', 'capacity' => 12000, 'available' => 5000, 'price' => 50],
                                ['name' => 'Peluza Sud', 'color' => '#8B5CF6', 'capacity' => 12000, 'available' => 6000, 'price' => 50],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Tribuna Oficială (VIP)', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 200, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-oficiala', 'description' => 'Loc numerotat, catering, parking'],
                            ['name' => 'Tribuna I', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 1500, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-1'],
                            ['name' => 'Tribuna II', 'price' => 80, 'currency' => 'RON', 'available' => true, 'remaining' => 3000, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2'],
                            ['name' => 'Peluza Nord', 'price' => 50, 'currency' => 'RON', 'available' => true, 'remaining' => 5000, 'has_seat_selection' => false, 'seat_zone' => 'peluza-nord'],
                            ['name' => 'Peluza Sud', 'price' => 50, 'currency' => 'RON', 'available' => true, 'remaining' => 6000, 'has_seat_selection' => false, 'seat_zone' => 'peluza-sud'],
                        ],
                    ],
                    [
                        'title' => 'Coldplay — Music of the Spheres Tour',
                        'date' => '+3m',
                        'time' => '20:00',
                        'type' => 'Concert',
                        'image' => '/demo/arena-concert.jpg',
                        'price_from' => 250,
                        'currency' => 'RON',
                        'has_seating_map' => true,
                        'seating_config' => [
                            'layout' => 'stadium-concert',
                            'zones' => [
                                ['name' => 'Golden Circle', 'color' => '#D4AF37', 'capacity' => 3000, 'available' => 150, 'price' => 700],
                                ['name' => 'Standing (Teren)', 'color' => '#DC2626', 'capacity' => 15000, 'available' => 2000, 'price' => 400],
                                ['name' => 'Tribuna I', 'color' => '#F59E0B', 'capacity' => 8000, 'available' => 800, 'price' => 350],
                                ['name' => 'Tribuna II', 'color' => '#3B82F6', 'capacity' => 10000, 'available' => 2500, 'price' => 250],
                            ],
                        ],
                        'ticket_types' => [
                            ['name' => 'Golden Circle', 'price' => 700, 'currency' => 'RON', 'available' => true, 'remaining' => 150, 'has_seat_selection' => false, 'seat_zone' => 'golden-circle', 'description' => 'Zona din fața scenei, acces exclusiv'],
                            ['name' => 'Standing (Teren)', 'price' => 400, 'currency' => 'RON', 'available' => true, 'remaining' => 2000, 'has_seat_selection' => false, 'seat_zone' => 'teren'],
                            ['name' => 'Tribuna I', 'price' => 350, 'currency' => 'RON', 'available' => true, 'remaining' => 800, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-1'],
                            ['name' => 'Tribuna II', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 2500, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2'],
                        ],
                    ],
                    [
                        'title' => 'FCSB vs CFR Cluj — Superliga',
                        'date' => '+15d',
                        'time' => '20:30',
                        'type' => 'Fotbal',
                        'image' => '/demo/arena-derby.jpg',
                        'price_from' => 35,
                        'currency' => 'RON',
                        'badge' => 'Derby',
                        'has_seating_map' => true,
                        'ticket_types' => [
                            ['name' => 'Tribuna I (VIP)', 'price' => 150, 'currency' => 'RON', 'available' => true, 'remaining' => 300, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-1'],
                            ['name' => 'Tribuna II', 'price' => 75, 'currency' => 'RON', 'available' => true, 'remaining' => 1500, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2'],
                            ['name' => 'Peluza Nord (gazdă)', 'price' => 35, 'currency' => 'RON', 'available' => true, 'remaining' => 3000, 'has_seat_selection' => false, 'seat_zone' => 'peluza-nord'],
                            ['name' => 'Peluza Sud (oaspeți)', 'price' => 35, 'currency' => 'RON', 'available' => true, 'remaining' => 2500, 'has_seat_selection' => false, 'seat_zone' => 'peluza-sud'],
                            ['name' => 'Lojă (10 pers.)', 'price' => 1500, 'currency' => 'RON', 'available' => true, 'remaining' => 4, 'has_seat_selection' => true, 'seat_zone' => 'loja', 'is_group' => true, 'description' => 'Lojă privată, catering, parking VIP'],
                        ],
                    ],
                    [
                        'title' => 'Monster Jam — Trucks Show',
                        'date' => '+75d',
                        'time' => '17:00',
                        'type' => 'Show',
                        'image' => '/demo/arena-trucks.jpg',
                        'price_from' => 120,
                        'currency' => 'RON',
                        'has_seating_map' => true,
                        'ticket_types' => [
                            ['name' => 'Tribuna I — Premium', 'price' => 250, 'currency' => 'RON', 'available' => true, 'remaining' => 500, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-1'],
                            ['name' => 'Tribuna II', 'price' => 180, 'currency' => 'RON', 'available' => true, 'remaining' => 2000, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2'],
                            ['name' => 'Peluza', 'price' => 120, 'currency' => 'RON', 'available' => true, 'remaining' => 4000, 'has_seat_selection' => false],
                            ['name' => 'Pachet Familie (2+2)', 'price' => 450, 'currency' => 'RON', 'available' => true, 'remaining' => 100, 'has_seat_selection' => true, 'seat_zone' => 'tribuna-2', 'is_group' => true, 'description' => '2 adulți + 2 copii, include pit pass'],
                        ],
                    ],
                ],
                'sectors' => [
                    ['name' => 'Golden Circle', 'type' => 'concert-only', 'price_range' => '350-700 RON'],
                    ['name' => 'Tribuna Oficială (VIP)', 'type' => 'all', 'price_range' => '150-400 RON'],
                    ['name' => 'Tribuna I', 'type' => 'all', 'price_range' => '120-350 RON'],
                    ['name' => 'Tribuna II', 'type' => 'all', 'price_range' => '80-250 RON'],
                    ['name' => 'Peluza Nord', 'type' => 'sport', 'price_range' => '35-120 RON'],
                    ['name' => 'Peluza Sud', 'type' => 'sport', 'price_range' => '35-120 RON'],
                ],
                'season_tickets' => [
                    ['name' => 'Abonament Tribuna I — Sezon 2026', 'price' => 1200, 'matches' => 17, 'perks' => ['Loc fix garantat', 'Parking gratuit', 'Acces VIP Lounge']],
                    ['name' => 'Abonament Peluza — Sezon 2026', 'price' => 400, 'matches' => 17, 'perks' => ['Loc în sector', 'Tricou oficial']],
                ],
                'facilities' => [
                    ['name' => 'Parking', 'capacity' => '5.000 locuri', 'icon' => 'car'],
                    ['name' => 'Food Court', 'description' => '20+ puncte de vânzare', 'icon' => 'utensils'],
                    ['name' => 'VIP Lounge', 'description' => 'Catering premium', 'icon' => 'star'],
                    ['name' => 'Acces Persoane cu Dizabilități', 'description' => 'Sectoare dedicate', 'icon' => 'accessibility'],
                ],
                'transport' => [
                    'metro' => 'Stația Piața Muncii (M1/M3) — 10 min pe jos',
                    'bus' => 'Linii 104, 330 — Stația Arena Națională',
                    'parking' => '5.000 locuri disponibile (20 RON/eveniment)',
                ],
                'stats' => [
                    'capacity' => 55634,
                    'events_per_year' => 80,
                    'total_visitors' => 2000000,
                    'opened_year' => 2011,
                ],
            ],
        ];
    }

    // ========================================================================
    // DEMO CUSTOMIZATIONS
    // ========================================================================
    private function getDemoCustomizations(WebTemplate $template): array
    {
        return match ($template->slug) {
            'organizator-classic' => [
                ['label' => 'Demo — EventPro România', 'unique_token' => 'demo-evpro-1', 'status' => 'active', 'customization_data' => ['brand_name' => 'EventPro România', 'primary_color' => '#4F46E5', 'phone' => '+40 721 234 567', 'email' => 'contact@eventpro.ro']],
            ],
            'marketplace-hub' => [
                ['label' => 'Demo — Tixello Marketplace', 'unique_token' => 'demo-mktpl-1', 'status' => 'active', 'customization_data' => ['marketplace_name' => 'Tixello', 'primary_color' => '#DC2626']],
            ],
            'agency-pro' => [
                ['label' => 'Demo — ArtistConnect Agency', 'unique_token' => 'demo-agncy-1', 'status' => 'active', 'customization_data' => ['agency_name' => 'ArtistConnect', 'primary_color' => '#0F172A', 'accent_color' => '#F43F5E']],
            ],
            'teatru-elegant' => [
                ['label' => 'Demo — Teatrul Național București', 'unique_token' => 'demo-teatr-1', 'status' => 'active', 'customization_data' => ['theater_name' => 'Teatrul Național „I. L. Caragiale"', 'primary_color' => '#7C2D12', 'gold_accent' => '#D4AF37', 'season_name' => 'Stagiunea 2025-2026']],
            ],
            'festival-vibrant' => [
                ['label' => 'Demo — SonicWave Festival', 'unique_token' => 'demo-fstvl-1', 'status' => 'active', 'customization_data' => ['festival_name' => 'SonicWave Festival', 'edition' => 'Ediția a VII-a', 'primary_color' => '#7C3AED']],
            ],
            'arena-sport' => [
                ['label' => 'Demo — Arena Națională', 'unique_token' => 'demo-arena-1', 'status' => 'active', 'customization_data' => ['arena_name' => 'Arena Națională București', 'primary_color' => '#1D4ED8']],
            ],
            default => [],
        };
    }
}
