<?php

namespace Database\Seeders;

use App\Models\Blog\BlogCategory;
use App\Models\MarketplaceClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The sixteen guide (blog) categories of Viaqui (marketplace client 4), in English, with everything the admin form
 * holds: description, picture, icon, colour, meta title and meta description.
 *
 * The keys are the category keys of the article batches (ImportBlogArticlesCommand::CATEGORIES), and the names are
 * the names that command looks for, so an import finds every category after this seeder has run.
 *
 * A category that is already in the admin is matched on its slug or on its name (letters and digits only, any case):
 * "Culture & History" is the same category as "Culture & history". Its name and slug are left as they are, and only
 * the fields that are still empty are filled, so nothing typed in the admin is lost. To write every field again:
 *
 *   VIAQUI_BLOG_CATEGORIES_OVERWRITE=1 php artisan db:seed --class="Database\Seeders\ViaquiBlogCategoriesSeeder"
 *
 * Pictures: database/data/blog-import/viaqui/categories/{key}.jpg, copied to the public disk under
 * blog-categories/. All sixteen are CC0 or public domain files from Wikimedia Commons (no credit line is needed);
 * their source is noted beside each category below.
 *
 * There are no parent categories: the guides page shows the categories as one row of topics.
 *
 *   php artisan db:seed --class="Database\Seeders\ViaquiBlogCategoriesSeeder"
 */
class ViaquiBlogCategoriesSeeder extends Seeder
{
    private const CLIENT_ID = 4;

    private const IMAGE_DIR = 'blog-categories';

    // key => [name, heroicon, colour, description, meta title (60 at most), meta description (160 at most)]
    private const CATEGORIES = [
        // Commons: Tram on Largo de Santo António da Sé, Lisbon, 2010.jpg (DimiTalen, CC0)
        'city-guides' => [
            'City guides', 'heroicon-o-building-office-2', '#2D6CCD',
            'One city at a time: a walking route through the old town, what is free and what needs a ticket, how long each stop takes, and which experiences are worth booking before you arrive.',
            'City guides: walks, free sights and tickets | Viaqui',
            'Practical guides to European cities: walking routes, what is free, what needs a ticket and what to book before you go.',
        ],
        // Commons: The Temple of Apollo in Ancient Corinth on June 6, 2018.jpg (George E. Koronaios, CC0)
        'culture-history' => [
            'Culture & history', 'heroicon-o-building-library', '#0F4D3A',
            'Museums, cathedrals, palaces and ancient sites, explained before you queue: which ticket covers what, how to see the main rooms in two hours, and the story that makes the visit worth the time.',
            'Culture & history guides: museums and monuments | Viaqui',
            'Which ticket, what order and how long: guides to Europe\'s museums, palaces, cathedrals and ancient sites, with the story behind each.',
        ],
        // Commons: Athens Acropolis Parthenon (27828101733).jpg (Gary Todd, CC0)
        'guided-tours' => [
            'Guided tours', 'heroicon-o-map', '#17663F',
            'Guided visits, audio tours and day trips compared honestly: what a tour includes and what it leaves out, when a guide is worth paying for, and when a ticket and a good map are enough.',
            'Guided tours and audio tours: what you get | Viaqui',
            'Guided tour, audio tour or just a ticket? What each one includes, what it leaves out and when a guide is worth it.',
        ],
        // Commons: Wiener Riesenrad 3.jpg (Dimitry Anikin, CC0)
        'family-kids' => [
            'Family & kids', 'heroicon-o-face-smile', '#38A169',
            'Days out that work with children: zoos, aquariums, theme parks and museums built for small hands, with the practical side covered, from height limits and queues to how long a visit really takes.',
            'Family days out in Europe: parks, zoos, museums | Viaqui',
            'Theme parks, zoos, aquariums and child-friendly museums across Europe: which to choose, which ticket and how long to allow.',
        ],
        // Commons: Karersee, Italy (Unsplash).jpg (Fab Lentz, CC0)
        'nature-outdoor' => [
            'Nature & outdoor', 'heroicon-o-sun', '#1F7D4D',
            'Volcanoes, gorges, caves, lakes and national parks: how to get there without a car, whether entry is timed, how hard the walk is, and what to wear and carry on the day.',
            'Nature & outdoor guides: parks, peaks and trails | Viaqui',
            'Guides to Europe\'s volcanoes, gorges, caves and national parks: how to get there, timed entry, how hard the walk is.',
        ],
        // Commons: 043-Barcelona-St-Josep-La-Boqueria.jpg (MartinThoma, CC0)
        'food-drink' => [
            'Food & drink', 'heroicon-o-cake', '#C6A15B',
            'Markets, food tours, tastings and cellar visits: what is on the table, what a tasting ticket includes, and how to fit a market or a vineyard into a day of sightseeing.',
            'Food & drink guides: markets, tastings, tours | Viaqui',
            'Food markets, tastings, cellar visits and food tours across Europe: what each ticket includes and how to fit one into your day.',
        ],
        // Commons: Old canal with bridge in sunny Fall in Amsterdam city, photo 2021 by Fons Heijnsbroek.jpg (Fons Heijnsbroek, CC0)
        'weekend-ideas' => [
            'Weekend ideas', 'heroicon-o-calendar-days', '#D9722E',
            'Two or three days, planned: day trips by train from the big cities, short breaks that fit a weekend, and the order to see things in so that nothing is rushed.',
            'Weekend ideas: short breaks and day trips | Viaqui',
            'Short breaks and day trips across Europe, planned for two or three days: where to go, how to get there and what to book.',
        ],
        // Commons: Sunset on the Seine, Paris 29 June 2015.jpg (Joe deSousa, CC0)
        'couples' => [
            'Couples & date night', 'heroicon-o-heart', '#C8322B',
            'Ideas for two: evening visits, boat rides at sunset, viewpoints, gardens and quiet museums, with the hours and the tickets worked out so the evening is not spent in a queue.',
            'Ideas for couples: evenings, views and boat rides | Viaqui',
            'Things to do for two in Europe\'s cities: evening visits, sunset boat rides, viewpoints and gardens, with tickets explained.',
        ],
        // Commons: Natural History Museum London (Unsplash).jpg (Claudio Testa, CC0)
        'rainy-day' => [
            'Rainy day / indoor', 'heroicon-o-cloud', '#4A6FA5',
            'What to do when the weather turns: museums, galleries, underground sites and covered markets, chosen by how long they keep you dry and by what the ticket really includes.',
            'Rainy day ideas: museums and indoor sights | Viaqui',
            'Where to go when it rains: museums, galleries, underground sites and indoor attractions in Europe\'s cities, ticket by ticket.',
        ],
        // Commons: Flea market on Torvet, Gamlebyen, Fredrikstad, 2006.jpg (DimiTalen, CC0)
        'budget-deals' => [
            'Budget & deals', 'heroicon-o-banknotes', '#8A6D2F',
            'Seeing more for less: free museums and free days, city passes that pay for themselves and passes that do not, combined tickets, and the sights you can enjoy from outside.',
            'Budget travel guides: free sights and city passes | Viaqui',
            'Free museums, free days, city passes and combined tickets across Europe: what saves money and what does not.',
        ],
        // Commons: Christmas market, 2015 - Heidelberg, Germany - DSC01521.jpg (Daderot, CC0)
        'seasonal' => [
            'Seasonal & holidays', 'heroicon-o-gift', '#B8862F',
            'The right place at the right time of year: Christmas markets, summer evenings, spring gardens and festivals, with what changes in each season, from opening hours to crowds.',
            'Seasonal guides: Christmas markets to summer | Viaqui',
            'Christmas markets, spring gardens, summer nights and festivals in Europe: when to go and what changes with the season.',
        ],
        // Commons: Paragliding in the Alps (Unsplash).jpg (Alexandre Chambon, CC0)
        'adventure' => [
            'Adventure & Adrenaline', 'heroicon-o-bolt', '#E43A33',
            'Zip lines, via ferrata, rafting, paragliding and tower climbs: what the experience involves, who it suits, the age, height and weight rules, and what to check before you pay.',
            'Adventure guides: zip lines, rafting, climbs | Viaqui',
            'Zip lines, via ferrata, rafting and paragliding in Europe: what to expect, who it suits and the rules to check before booking.',
        ],
        // Commons: View of Schenyi baths outdoor pool..JPG (Rudolph.A.furtado, CC0)
        'wellness' => [
            'Wellness & relax', 'heroicon-o-sparkles', '#5FA8A0',
            'Thermal baths, spas and hot springs: how a visit works, cabin or locker, what to bring, which pools are included in the ticket, and the quiet hours when the water is yours.',
            'Thermal baths and spas in Europe: how they work | Viaqui',
            'Thermal baths, spas and hot springs across Europe: tickets, cabins and lockers, what to bring and the quiet hours to go.',
        ],
        // Commons: Széchenyi Chain Bridge in Budapest at night.jpg (Wilfredor, CC0)
        'nightlife' => [
            'Nightlife', 'heroicon-o-moon', '#1F2937',
            'Cities after dark: evening openings of museums and monuments, night walks, concerts, shows and river cruises, with the last entry times that catch visitors out.',
            'Europe after dark: night visits, shows, cruises | Viaqui',
            'Evening museum openings, night walks, concerts, shows and river cruises in Europe\'s cities, with last entry times explained.',
        ],
        // Commons: Hedge maze in Parque São Roque da Lameira.jpg (Joseolgon, CC0)
        'escape-rooms' => [
            'Escape rooms & games', 'heroicon-o-puzzle-piece', '#07291F',
            'Escape rooms, mazes, city treasure hunts and immersive games: how long they last, how many players they need, which suit children, and which work when nobody speaks the local language.',
            'Escape rooms, mazes and city games in Europe | Viaqui',
            'Escape rooms, mazes, treasure hunts and immersive games across Europe: group size, duration, language and age limits.',
        ],
        // Commons: Rafting, Fırtına Deresi, Rize 2014.jpg (Hamdigumus, CC0)
        'team-building' => [
            'Team building', 'heroicon-o-user-group', '#56615B',
            'Activities for groups and teams: rafting, cooking classes, city games and private tours, with the group sizes they suit and what to ask the operator before you book for twenty people.',
            'Team building ideas: group activities in Europe | Viaqui',
            'Group and team activities across Europe: rafting, cooking classes, city games and private tours, and what to ask before booking.',
        ],
    ];

    public function run(): void
    {
        $client = MarketplaceClient::find(self::CLIENT_ID);
        if (! $client) {
            $this->command?->warn('Marketplace client ' . self::CLIENT_ID . ' not found: nothing seeded.');

            return;
        }

        $lang = $client->language ?? $client->locale ?? 'en';
        $overwrite = (bool) env('VIAQUI_BLOG_CATEGORIES_OVERWRITE', false);
        $normalize = fn ($name) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $name));

        $existing = BlogCategory::where('marketplace_client_id', self::CLIENT_ID)->get();
        $created = 0;
        $filled = 0;
        $sort = 0;

        foreach (self::CATEGORIES as $key => [$name, $icon, $color, $description, $metaTitle, $metaDescription]) {
            $sort += 10;
            $category = $existing->firstWhere('slug', $key)
                ?? $existing->first(fn (BlogCategory $c) => in_array($normalize($name), array_map($normalize, (array) $c->name), true));

            if (! $category) {
                $category = new BlogCategory([
                    'marketplace_client_id' => self::CLIENT_ID,
                    'tenant_id' => null,
                    'slug' => $key,
                    'name' => [$lang => $name],
                    'is_visible' => true,
                ]);
                $created++;
            }

            $values = [
                'description' => $description,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ];
            foreach ($values as $field => $text) {
                $translations = (array) $category->{$field};
                if ($overwrite || trim((string) ($translations[$lang] ?? '')) === '') {
                    $translations[$lang] = $text;
                    $category->{$field} = $translations;
                }
            }
            if ($overwrite || ! $category->icon) {
                $category->icon = $icon;
            }
            if ($overwrite || ! $category->color) {
                $category->color = $color;
            }
            if ($overwrite || ! $category->image_url) {
                $category->image_url = $this->storeImage($key) ?? $category->image_url;
            }
            if ($overwrite || ! $category->exists || ! $category->sort_order) {
                $category->sort_order = $sort;
            }

            if ($category->isDirty()) {
                $filled++;
                $category->save();
            }
        }

        $this->command?->info('Viaqui guide categories: ' . count(self::CATEGORIES) . " in the list, {$created} created, {$filled} written.");
    }

    /** Copy the category picture to the public disk; returns its public URL, or null when the file is missing. */
    private function storeImage(string $key): ?string
    {
        $source = database_path("data/blog-import/viaqui/categories/{$key}.jpg");
        if (! File::exists($source)) {
            $this->command?->warn("No picture for {$key}: {$source}");

            return null;
        }

        $target = self::IMAGE_DIR . "/viaqui-{$key}.jpg";
        Storage::disk('public')->put($target, File::get($source));

        return Storage::disk('public')->url($target);
    }
}
