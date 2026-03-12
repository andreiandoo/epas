<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Artist;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\SmsCampaign;
use App\Models\SmsCredit;
use App\Models\Venue;
use App\Jobs\SendSmsCampaignJob;

class SmsCampaigns extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Campanii SMS';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?string $navigationParentItem = 'Notificări SMS';
    protected static ?int $navigationSort = 16;
    protected string $view = 'filament.marketplace.pages.sms-campaigns';

    // Campaign form
    public string $campaignName = '';
    public string $messageText = '';
    public string $filterOrganizer = '';
    public string $filterEvent = '';
    public array $filterCities = [];
    public array $filterArtists = [];
    public array $filterGenres = [];
    public array $filterVenues = [];
    public ?string $scheduledAt = null;

    // Audience preview (calculated)
    public int $totalAudience = 0;
    public int $audienceWithPhone = 0;

    // UI state
    public string $activeView = 'list'; // 'list' or 'create'
    public ?int $editingCampaignId = null;

    public function getTitle(): string
    {
        return 'Campanii SMS';
    }

    public function switchToCreate(): void
    {
        $this->resetForm();
        $this->activeView = 'create';
    }

    public function switchToList(): void
    {
        $this->activeView = 'list';
        $this->editingCampaignId = null;
    }

    public function editCampaign(int $id): void
    {
        $campaign = SmsCampaign::find($id);
        if (!$campaign || $campaign->status !== 'draft') {
            return;
        }

        $this->editingCampaignId = $campaign->id;
        $this->campaignName = $campaign->name;
        $this->messageText = $campaign->message_text;
        $this->filterOrganizer = (string) ($campaign->marketplace_organizer_id ?? '');
        $this->filterEvent = (string) ($campaign->event_id ?? '');
        $filters = $campaign->filters ?? [];
        $this->filterCities = $filters['city_ids'] ?? [];
        $this->filterArtists = $filters['artist_ids'] ?? [];
        $this->filterGenres = $filters['genre_ids'] ?? [];
        $this->filterVenues = $filters['venue_ids'] ?? [];
        $this->scheduledAt = $campaign->scheduled_at?->format('Y-m-d\TH:i');
        $this->activeView = 'create';
        $this->calculateAudience();
    }

    protected function resetForm(): void
    {
        $this->campaignName = '';
        $this->messageText = '';
        $this->filterOrganizer = '';
        $this->filterEvent = '';
        $this->filterCities = [];
        $this->filterArtists = [];
        $this->filterGenres = [];
        $this->filterVenues = [];
        $this->scheduledAt = null;
        $this->totalAudience = 0;
        $this->audienceWithPhone = 0;
        $this->editingCampaignId = null;
    }

    public function updatedFilterOrganizer(): void
    {
        $this->filterEvent = '';
    }

    public function calculateAudience(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        $query = $this->buildAudienceQuery($marketplace);
        $this->totalAudience = (clone $query)->count();
        $this->audienceWithPhone = (clone $query)->whereNotNull('phone')->where('phone', '!=', '')->count();
    }

    protected function buildAudienceQuery($marketplace)
    {
        $query = MarketplaceCustomer::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active');

        if (!empty($this->filterCities)) {
            $query->where(function ($q) {
                $cityNames = MarketplaceCity::whereIn('id', $this->filterCities)
                    ->get()
                    ->flatMap(fn ($city) => array_values(is_array($city->name) ? $city->name : [$city->name]))
                    ->map(fn ($name) => strtolower(trim($name)))
                    ->unique()
                    ->toArray();

                if (!empty($cityNames)) {
                    $q->where(function ($qq) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $qq->orWhereRaw('LOWER(TRIM(city)) = ?', [$name]);
                        }
                    });
                }

                $q->orWhereHas('orders', function ($oq) {
                    $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                        ->whereHas('event', function ($eq) {
                            $eq->whereIn('marketplace_city_id', $this->filterCities);
                        });
                });
            });
        }

        if (!empty($this->filterArtists)) {
            $query->where(function ($q) {
                $q->whereHas('orders', function ($oq) {
                    $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                        ->whereHas('event', function ($eq) {
                            $eq->whereHas('artists', function ($aq) {
                                $aq->whereIn('artists.id', $this->filterArtists);
                            });
                        });
                });
                $q->orWhereHas('favoriteArtists', function ($fq) {
                    $fq->whereIn('artists.id', $this->filterArtists);
                });
            });
        }

        if (!empty($this->filterGenres)) {
            $query->whereHas('orders', function ($oq) {
                $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                    ->whereHas('event', function ($eq) {
                        $eq->whereHas('eventGenres', function ($gq) {
                            $gq->whereIn('event_genres.id', $this->filterGenres);
                        });
                    });
            });
        }

        if (!empty($this->filterVenues)) {
            $query->whereHas('orders', function ($oq) {
                $oq->whereIn('status', ['completed', 'paid', 'confirmed'])
                    ->whereHas('event', function ($eq) {
                        $eq->whereIn('venue_id', $this->filterVenues);
                    });
            });
        }

        return $query;
    }

    public function saveDraft(): void
    {
        $this->saveCampaign('draft');
        $this->activeView = 'list';
    }

    public function saveAndSchedule(): void
    {
        if (!$this->scheduledAt) {
            return;
        }
        $this->saveCampaign('scheduled');
        $this->activeView = 'list';
    }

    public function sendNow(): void
    {
        $campaign = $this->saveCampaign('scheduled');
        if ($campaign) {
            SendSmsCampaignJob::dispatch($campaign->id);
        }
        $this->activeView = 'list';
    }

    protected function saveCampaign(string $status): ?SmsCampaign
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace || empty($this->campaignName) || empty($this->messageText)) {
            return null;
        }

        $this->calculateAudience();

        $smsPerRecipient = SmsCampaign::calculateSmsCount($this->messageText);
        $totalSmsNeeded = $this->audienceWithPhone * $smsPerRecipient;

        $data = [
            'marketplace_client_id' => $marketplace->id,
            'name' => $this->campaignName,
            'status' => $status,
            'message_text' => $this->messageText,
            'marketplace_organizer_id' => $this->filterOrganizer ?: null,
            'event_id' => $this->filterEvent ?: null,
            'filters' => [
                'city_ids' => $this->filterCities,
                'artist_ids' => $this->filterArtists,
                'genre_ids' => $this->filterGenres,
                'venue_ids' => $this->filterVenues,
            ],
            'total_audience' => $this->totalAudience,
            'audience_with_phone' => $this->audienceWithPhone,
            'sms_per_recipient' => $smsPerRecipient,
            'total_sms_needed' => $totalSmsNeeded,
            'scheduled_at' => $this->scheduledAt ? \Carbon\Carbon::parse($this->scheduledAt) : null,
        ];

        if ($this->editingCampaignId) {
            $campaign = SmsCampaign::find($this->editingCampaignId);
            if ($campaign && $campaign->status === 'draft') {
                $campaign->update($data);
                return $campaign;
            }
        }

        return SmsCampaign::create($data);
    }

    public function cancelCampaign(int $id): void
    {
        $campaign = SmsCampaign::find($id);
        if ($campaign && in_array($campaign->status, ['draft', 'scheduled'])) {
            $campaign->update(['status' => 'cancelled']);
        }
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return [
                'campaigns' => collect(),
                'organizerOptions' => [],
                'eventOptions' => [],
                'cityOptions' => [],
                'artistOptions' => [],
                'genreOptions' => [],
                'venueOptions' => [],
                'promotionalCredits' => 0,
                'promotionalPrice' => 0.50,
            ];
        }

        $campaigns = SmsCampaign::where('marketplace_client_id', $marketplace->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $organizerOptions = MarketplaceOrganizer::where('marketplace_client_id', $marketplace->id)
            ->whereNotNull('verified_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $eventOptions = [];
        if ($this->filterOrganizer) {
            $eventOptions = Event::where('marketplace_client_id', $marketplace->id)
                ->where('marketplace_organizer_id', $this->filterOrganizer)
                ->orderByDesc('event_date')
                ->get()
                ->mapWithKeys(function ($event) {
                    $title = $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: 'Event #' . $event->id;
                    return [$event->id => $title];
                })
                ->toArray();
        }

        $cityOptions = MarketplaceCity::where('marketplace_client_id', $marketplace->id)
            ->where('is_visible', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($city) => [$city->id => $city->getTranslation('name', 'ro') ?: $city->getTranslation('name', 'en')])
            ->toArray();

        $artistOptions = Artist::where(function ($q) use ($marketplace) {
                $q->where('marketplace_client_id', $marketplace->id)
                  ->orWhereNull('marketplace_client_id');
            })
            ->orderBy('name')
            ->limit(200)
            ->pluck('name', 'id')
            ->toArray();

        $genreOptions = EventGenre::orderBy('name')
            ->get()
            ->mapWithKeys(fn ($g) => [$g->id => $g->getTranslation('name', 'ro') ?: $g->getTranslation('name', 'en') ?: $g->name])
            ->toArray();

        $venueOptions = Venue::where(function ($q) use ($marketplace) {
                $q->where('marketplace_client_id', $marketplace->id)
                  ->orWhereNull('marketplace_client_id')
                  ->orWhereHas('marketplaceClients', fn ($q2) => $q2->where('marketplace_client_id', $marketplace->id));
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->id => ($v->getTranslation('name', app()->getLocale()) ?: $v->name) . ($v->city ? ' (' . $v->city . ')' : '')])
            ->toArray();

        $promotionalCredits = SmsCredit::getAvailableCredits($marketplace, 'promotional');

        // Get SMS pricing
        $microservice = \App\Models\Microservice::where('slug', 'sms-notifications')->first();
        $promotionalPrice = (float) ($microservice->metadata['sms_pricing']['promotional']['price'] ?? 0.50);

        // Currency conversion for RON clients
        $clientCurrency = strtoupper($marketplace->currency ?? 'EUR');
        $eurToRon = null;
        if ($clientCurrency === 'RON') {
            $eurToRon = \App\Models\ExchangeRate::getLatestRate('EUR', 'RON');
        }

        return [
            'campaigns' => $campaigns,
            'organizerOptions' => $organizerOptions,
            'eventOptions' => $eventOptions,
            'cityOptions' => $cityOptions,
            'artistOptions' => $artistOptions,
            'genreOptions' => $genreOptions,
            'venueOptions' => $venueOptions,
            'promotionalCredits' => $promotionalCredits,
            'promotionalPrice' => $promotionalPrice,
            'clientCurrency' => $clientCurrency,
            'eurToRon' => $eurToRon,
        ];
    }
}
