<?php

namespace Database\Seeders;

use App\Models\MarketplaceCityIntent;
use Illuminate\Database\Seeder;

/**
 * Viaqui (marketplace client 4): the four "ideas" landings the site links to.
 *
 *   /weekend-ideas, /with-kids, /rainy-days, /for-couples   and the same under a city (/london/weekend-ideas)
 *
 * The site maps those English addresses onto the intent slugs below (its .htaccess), and its page keeps filter rules
 * keyed on the same slugs, so the slugs are the ones of MarketplaceCityIntentsSeeder. Only the words differ: Viaqui is
 * in English and speaks about Europe, not about one country. The texts are stored under both 'en' and 'ro' because
 * the API falls back to 'ro' when a client has no language set.
 *
 * Without these rows the API answers "intent not found" and every one of those addresses is a 404.
 *
 * Run:  php artisan tinker --execute="require base_path('database/seeders/ViaquiIntentsSeeder.php'); (new Database\Seeders\ViaquiIntentsSeeder)->run();"
 * Idempotent: running it again updates the rows in place.
 */
class ViaquiIntentsSeeder extends Seeder
{
    public const CLIENT_ID = 4;

    public function run(): void
    {
        foreach ($this->intents() as $order => $i) {
            $both = fn (string $text) => ['en' => $text, 'ro' => $text];
            MarketplaceCityIntent::updateOrCreate(
                ['marketplace_client_id' => self::CLIENT_ID, 'slug' => $i['slug']],
                [
                    'name' => $both($i['name']),
                    'title_template' => $both($i['title']),
                    'h1_template' => $both($i['h1']),
                    'meta_description_template' => $both($i['meta']),
                    'intro_copy' => $both($i['intro']),
                    'seo_copy' => $both($i['seo']),
                    'filter_rule_json' => $i['rule'],
                    'icon' => $i['icon'],
                    'accent_color' => $i['accent'],
                    // an empty landing is still worth a page here: the site shows what is available meanwhile
                    'min_results_for_index' => 3,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                ]
            );
            echo '  ' . $i['slug'] . "\n";
        }
        echo 'Viaqui intents: ' . MarketplaceCityIntent::where('marketplace_client_id', self::CLIENT_ID)->count() . "\n";
    }

    private function intents(): array
    {
        return [
            [
                'slug' => 'activitati-weekend', 'name' => 'Weekend', 'icon' => '🎉', 'accent' => 'ochre',
                'title' => 'Weekend ideas in {city_name} · Viaqui',
                'h1' => 'What to do this weekend in {city_name}',
                'meta' => 'Things to do this weekend in {city_name}: tours, attractions and experiences with places left on Saturday and Sunday. Book online, get in with your phone.',
                'intro' => 'The weekend is short. Choose from what still has places on Saturday and Sunday.',
                'seo' => 'The weekend selection for {city_name}: experiences that suit a day out with friends, family or children, with checked schedules and tickets on your phone.',
                'rule' => ['all' => [['type' => 'in_city', 'param' => '$city'], ['type' => 'has_session_this_weekend']]],
            ],
            [
                'slug' => 'activitati-copii', 'name' => 'With children', 'icon' => '🧒', 'accent' => 'ochre',
                'title' => 'Things to do with children in {city_name} · Viaqui',
                'h1' => 'Things to do with children in {city_name}',
                'meta' => 'Things to do with children in {city_name}: museums, workshops, parks and experiences that suit their age.',
                'intro' => 'Places where children are welcome and have something made for them.',
                'seo' => 'In {city_name} there is plenty made with children in mind: hands-on museums, workshops, planetariums and adventure parks. Each listing says from what age it is suitable.',
                'rule' => ['all' => [['type' => 'in_city', 'param' => '$city'], ['type' => 'event_attr', 'field' => 'is_kid_friendly', 'value' => true]]],
            ],
            [
                'slug' => 'activitati-zile-ploioase', 'name' => 'Rainy days', 'icon' => '🌧️', 'accent' => 'sky',
                'title' => 'Things to do on a rainy day in {city_name} · Viaqui',
                'h1' => 'Things to do on a rainy day in {city_name}',
                'meta' => 'Raining? Here is what you can do in {city_name}: indoor experiences with set times, bookable online.',
                'intro' => 'Things to do under a roof, for the days the weather does not help.',
                'seo' => 'Rain does not have to spoil the day. These are the indoor experiences in {city_name}: places you can walk straight into, where the weather makes no difference.',
                'rule' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_indoor', 'value' => true],
                    ['type' => 'event_attr', 'field' => 'is_weather_sensitive', 'value' => false],
                ]],
            ],
            [
                'slug' => 'activitati-cuplu', 'name' => 'For couples', 'icon' => '💑', 'accent' => 'vermilion',
                'title' => 'Things to do for couples in {city_name} · Viaqui',
                'h1' => 'Things to do for two in {city_name}',
                'meta' => 'Date ideas and things to do for two in {city_name}: tastings, workshops, evening tours and gift experiences.',
                'intro' => 'Ideas for the two of you, with no queueing.',
                'seo' => 'Things to do in {city_name} for two: tastings, creative workshops, evening tours and experiences that can be booked for a couple.',
                'rule' => ['all' => [['type' => 'in_city', 'param' => '$city'], ['type' => 'tag', 'value' => 'cuplu']]],
            ],
        ];
    }
}
