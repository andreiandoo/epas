<?php

namespace App\Services;

use App\Models\AffiliateEventSource;
use App\Models\Event;
use App\Models\MarketplaceCity;
use App\Models\Venue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketMasterImportService
{
    protected const BASE_URL = 'https://app.ticketmaster.com/discovery/v2';

    protected string $apiKey;
    protected AffiliateEventSource $source;
    protected int $marketplaceClientId;

    public function __construct(AffiliateEventSource $source)
    {
        $this->source = $source;
        $this->marketplaceClientId = $source->marketplace_client_id;
        $this->apiKey = $source->settings['ticketmaster_api_key'] ?? '';
    }

    /**
     * Search events from TicketMaster API
     */
    public function searchEvents(array $params = []): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('TicketMaster API key not configured for this source.');
        }

        $defaults = [
            'apikey' => $this->apiKey,
            'size' => $params['size'] ?? 50,
            'page' => $params['page'] ?? 0,
            'sort' => 'date,asc',
            'includeTBA' => 'no',
            'includeTBD' => 'no',
            'includeTest' => 'no',
        ];

        // Merge user params (overrides defaults except apikey)
        $query = array_merge($defaults, $params);
        $query['apikey'] = $this->apiKey; // Always use our key

        $response = Http::timeout(30)
            ->retry(3, 2000)
            ->get(self::BASE_URL . '/events.json', $query);

        if ($response->status() === 429) {
            throw new \RuntimeException('TicketMaster API rate limit exceeded. Try again later.');
        }

        if (!$response->successful()) {
            throw new \RuntimeException('TicketMaster API error: ' . $response->status() . ' - ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Import events from search results into the database.
     * Returns array with 'imported' and 'skipped' counts.
     */
    public function importEvents(array $params = []): array
    {
        $data = $this->searchEvents($params);

        $events = $data['_embedded']['events'] ?? [];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($events as $tmEvent) {
            try {
                $ticketMasterId = $tmEvent['id'] ?? null;
                if (!$ticketMasterId) {
                    $skipped++;
                    continue;
                }

                // Skip if already imported (check by ticketmaster ID in affiliate_data)
                $existing = Event::where('marketplace_client_id', $this->marketplaceClientId)
                    ->where('is_affiliate', true)
                    ->where('affiliate_event_source_id', $this->source->id)
                    ->whereJsonContains('affiliate_data->ticketmaster_id', $ticketMasterId)
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                $this->createEventFromTicketMaster($tmEvent);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = ($tmEvent['name'] ?? 'Unknown') . ': ' . $e->getMessage();
                Log::warning('TicketMaster import error', [
                    'event_name' => $tmEvent['name'] ?? 'Unknown',
                    'ticketmaster_id' => $tmEvent['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_available' => $data['page']['totalElements'] ?? 0,
            'page' => $data['page']['number'] ?? 0,
            'total_pages' => $data['page']['totalPages'] ?? 0,
        ];
    }

    /**
     * Create a single Event record from TicketMaster event data
     */
    protected function createEventFromTicketMaster(array $tmEvent): Event
    {
        $name = $tmEvent['name'] ?? 'Unnamed Event';
        $language = $this->source->marketplaceClient->language
            ?? $this->source->marketplaceClient->locale
            ?? 'ro';

        // Build title (translatable array)
        $title = [$language => $name];

        // Build slug
        $nextId = (Event::max('id') ?? 0) + 1;
        $slug = Str::slug($name) . '-' . $nextId;

        // Parse dates
        $dates = $tmEvent['dates'] ?? [];
        $startDate = $dates['start']['localDate'] ?? null;
        $startTime = $dates['start']['localTime'] ?? null;
        $endDate = $dates['end']['localDate'] ?? null;
        $endTime = $dates['end']['localTime'] ?? null;

        // Determine duration mode
        $durationMode = 'single_day';
        if ($startDate && $endDate && $startDate !== $endDate) {
            $durationMode = 'range';
        }

        // Parse price
        $priceRanges = $tmEvent['priceRanges'] ?? [];
        $targetPrice = null;
        if (!empty($priceRanges)) {
            $targetPrice = $priceRanges[0]['min'] ?? $priceRanges[0]['max'] ?? null;
        }

        // Parse venue
        $venues = $tmEvent['_embedded']['venues'] ?? [];
        $venueData = $venues[0] ?? null;
        $address = null;
        $venueName = null;
        $cityName = null;

        if ($venueData) {
            $venueName = $venueData['name'] ?? null;
            $cityName = $venueData['city']['name'] ?? null;
            $address = ($venueData['address']['line1'] ?? '') . ($cityName ? ', ' . $cityName : '');
        }

        // Try to match marketplace city
        $marketplaceCityId = null;
        if ($cityName) {
            $matchedCity = MarketplaceCity::where('marketplace_client_id', $this->marketplaceClientId)
                ->where('is_visible', true)
                ->get()
                ->first(function ($city) use ($cityName) {
                    $nameVariants = is_array($city->name) ? $city->name : [];
                    foreach ($nameVariants as $lang => $n) {
                        if (strtolower(trim($n)) === strtolower(trim($cityName))) {
                            return true;
                        }
                    }
                    return false;
                });
            $marketplaceCityId = $matchedCity?->id;
        }

        // Parse images - pick best poster and hero image
        $images = $tmEvent['images'] ?? [];
        $posterUrl = null;
        $heroImageUrl = null;

        foreach ($images as $img) {
            $ratio = $img['ratio'] ?? '';
            $url = $img['url'] ?? '';
            if (!$url) continue;

            if ($ratio === '3_2' && !$posterUrl) {
                $posterUrl = $url;
            } elseif ($ratio === '16_9' && !$heroImageUrl) {
                $heroImageUrl = $url;
            }
        }
        // Fallback: use first available image
        if (!$posterUrl && !empty($images[0]['url'])) {
            $posterUrl = $images[0]['url'];
        }

        // Build description from info + pleaseNote
        $descriptionParts = [];
        if (!empty($tmEvent['info'])) {
            $descriptionParts[] = $tmEvent['info'];
        }
        if (!empty($tmEvent['pleaseNote'])) {
            $descriptionParts[] = '<p><strong>Note:</strong> ' . $tmEvent['pleaseNote'] . '</p>';
        }
        if ($venueName) {
            $descriptionParts[] = '<p><strong>Venue:</strong> ' . e($venueName) . '</p>';
        }
        $description = implode("\n", $descriptionParts);

        // Ticket purchase URL
        $affiliateUrl = $tmEvent['url'] ?? null;

        // Build affiliate_data with all raw TicketMaster metadata
        $affiliateData = [
            'ticketmaster_id' => $tmEvent['id'],
            'source_url' => $affiliateUrl,
            'classifications' => $tmEvent['classifications'] ?? [],
            'price_ranges' => $priceRanges,
            'venue' => $venueData,
            'dates_status' => $dates['status']['code'] ?? null,
            'sales' => $tmEvent['sales'] ?? [],
            'imported_at' => now()->toIso8601String(),
        ];

        // Create event data array
        $eventData = [
            'marketplace_client_id' => $this->marketplaceClientId,
            'is_affiliate' => true,
            'affiliate_event_source_id' => $this->source->id,
            'affiliate_url' => $affiliateUrl,
            'affiliate_data' => $affiliateData,
            'title' => $title,
            'slug' => $slug,
            'duration_mode' => $durationMode,
            'is_published' => true,
            'target_price' => $targetPrice,
            'address' => $address,
            'marketplace_city_id' => $marketplaceCityId,
            'poster_url' => $posterUrl,
            'hero_image_url' => $heroImageUrl,
        ];

        // Set description (translatable)
        if ($description) {
            $eventData['description'] = [$language => $description];
        }

        // Set date fields based on duration mode
        if ($durationMode === 'single_day') {
            $eventData['event_date'] = $startDate;
            $eventData['start_time'] = $startTime ? substr($startTime, 0, 5) : null;
            $eventData['end_time'] = $endTime ? substr($endTime, 0, 5) : null;
        } else {
            $eventData['range_start_date'] = $startDate;
            $eventData['range_end_date'] = $endDate;
            $eventData['range_start_time'] = $startTime ? substr($startTime, 0, 5) : null;
            $eventData['range_end_time'] = $endTime ? substr($endTime, 0, 5) : null;
        }

        $event = Event::create($eventData);

        // Fix slug and event_series with actual ID
        $event->slug = Str::slug($name) . '-' . $event->id;
        $event->event_series = 'AMB-' . $event->id;
        $event->saveQuietly();

        return $event;
    }

    /**
     * Get available countries from TicketMaster
     */
    public static function getCountryOptions(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'DE' => 'Germany',
            'AT' => 'Austria',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'NO' => 'Norway',
            'SE' => 'Sweden',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'FR' => 'France',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'MX' => 'Mexico',
        ];
    }

    /**
     * Get classification/category options
     */
    public static function getClassificationOptions(): array
    {
        return [
            'music' => 'Music',
            'sports' => 'Sports',
            'arts' => 'Arts & Theatre',
            'film' => 'Film',
            'miscellaneous' => 'Miscellaneous',
        ];
    }
}
