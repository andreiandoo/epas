<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Venue extends Model
{
    use Translatable;

    /**
     * Translatable fields
     */
    public array $translatable = ['name', 'description'];

    /**
     * Venue tag options with emoji icons
     */
    public const TAG_OPTIONS = [
        'historic' => ['label' => 'Istoric', 'icon' => '🏛️'],
        'popular' => ['label' => 'Popular', 'icon' => '⭐'],
    ];

    /**
     * Venue facilities organized by category
     */
    public const FACILITIES = [
        'infrastructure' => [
            'label' => '🏢 Infrastructură & Acces',
            'items' => [
                'parking' => '🅿️ Parcare',
                'parking_underground' => '🅿️ Parcare subterană',
                'parking_vip' => '🅿️ Parcare VIP',
                'wheelchair_access' => '♿ Acces persoane cu dizabilități',
                'elevator' => '🛗 Lift / Elevator',
                'artist_entrance' => '🚪 Intrare separată artiști',
                'vip_entrance' => '🚪 Intrare VIP',
                'truck_access' => '🚐 Acces TIR / Camion decor',
                'metro_nearby' => '🚇 Aproape de metrou',
                'public_transport' => '🚌 Stație transport public',
                'taxi_zone' => '🚕 Zonă taxi / rideshare',
                'bike_parking' => '🚲 Parcare biciclete',
                'ev_charging' => '⚡ Stație încărcare electrice',
            ],
        ],
        'stage_production' => [
            'label' => '🎭 Scenă & Producție',
            'items' => [
                'main_stage' => '🎭 Scenă principală',
                'secondary_stage' => '🎭 Scenă secundară',
                'modular_stage' => '🎭 Scenă mobilă / modulară',
                'orchestra_pit' => '🎭 Fosă orchestră',
                'backstage' => '🎬 Backstage',
                'artist_cabins' => '👔 Cabine artiști',
                'makeup_rooms' => '👗 Cabine machiaj',
                'artist_wardrobe' => '🪞 Garderobă artiști',
                'artist_showers' => '🚿 Dușuri artiști',
                'artist_catering' => '🍽️ Catering artiști',
                'green_room' => '🛋️ Green room / Lounge artiști',
                'equipment_storage' => '📦 Depozit echipamente',
                'loading_zone' => '🏗️ Zonă încărcare/descărcare',
            ],
        ],
        'audio_video' => [
            'label' => '🎵 Audio & Video',
            'items' => [
                'pro_audio' => '🎵 Sistem audio profesional',
                'pa_system' => '🔊 Sistem PA',
                'audio_mixer' => '🎚️ Mixer audio',
                'wireless_mics' => '🎤 Microfoane wireless',
                'stage_monitors' => '🎧 Sistem monitoare scenă',
                'led_screens' => '📺 Ecrane LED',
                'video_projector' => '📽️ Proiector video',
                'livestreaming' => '🎥 Sistem livestreaming',
                'fixed_cameras' => '📹 Camere video fixe',
                'av_control_room' => '🖥️ Regie audio/video',
            ],
        ],
        'lighting' => [
            'label' => '💡 Iluminat',
            'items' => [
                'pro_stage_lighting' => '💡 Iluminat scenă profesional',
                'rgb_led_lights' => '🌈 Lumini LED RGB',
                'special_effects' => '✨ Efecte speciale lumini',
                'followspot' => '🔦 Followspot',
                'laser_show' => '💫 Laser show',
                'disco_ball' => '🪩 Glob disco',
                'indoor_pyro' => '🎆 Sistem pirotehnic indoor',
                'fog_machine' => '🌫️ Mașină de fum',
                'snow_machine' => '❄️ Mașină de zăpadă artificială',
                'bubble_machine' => '🫧 Mașină de bule',
                'confetti' => '🎊 Confetti / Streamer',
            ],
        ],
        'seating' => [
            'label' => '🪑 Locuri & Amenajare',
            'items' => [
                'fixed_seats' => '🪑 Scaune fixe',
                'mobile_seats' => '🪑 Scaune mobile / pliante',
                'lounge_zones' => '🛋️ Zone lounge',
                'standing_area' => '🎪 Standing area',
                'tribunes' => '🏟️ Tribune',
                'vip_boxes' => '👑 Loje VIP',
                'golden_circle' => '🎫 Zona Golden Circle',
                'raised_platform' => '🧍 Platformă înălțată',
                'configurable_layout' => '📐 Layout configurabil',
                'view_360' => '🎯 Vizibilitate 360°',
            ],
        ],
        'food_beverage' => [
            'label' => '🍽️ Food & Beverage',
            'items' => [
                'restaurant' => '🍽️ Restaurant',
                'fast_food' => '🍕 Fast food',
                'food_court' => '🍔 Food court',
                'cafe' => '☕ Cafenea',
                'bar' => '🍺 Bar',
                'cocktail_bar' => '🍸 Cocktail bar',
                'wine_bar' => '🍷 Wine bar',
                'vip_bar' => '🥂 Bar VIP',
                'mobile_bar' => '🧊 Bar mobil',
                'snack_bar' => '🍿 Snack bar',
                'food_trucks' => '🚚 Food trucks',
                'catering_available' => '🍴 Catering disponibil',
                'vegetarian_options' => '🥗 Opțiuni vegetariene/vegane',
                'gluten_free' => '🌾 Opțiuni fără gluten',
            ],
        ],
        'general_facilities' => [
            'label' => '🚻 Facilități Generale',
            'items' => [
                'toilets' => '🚻 Toalete',
                'toilets_women' => '🚺 Toalete femei',
                'toilets_men' => '🚹 Toalete bărbați',
                'accessible_toilets' => '♿ Toalete accesibile',
                'baby_room' => '🚼 Cameră pentru bebeluși',
                'nursing_room' => '👶 Spațiu alăptare',
                'cloakroom' => '🧥 Garderobă',
                'lockers' => '🔐 Dulapuri / Lockers',
                'smoking_area' => '💨 Zonă fumători',
                'non_smoking' => '🚭 Zonă non-fumători',
                'atm' => '🏧 ATM / Bancomat',
                'card_payment' => '💳 Plată card / contactless',
            ],
        ],
        'climate' => [
            'label' => '❄️ Climatizare & Confort',
            'items' => [
                'air_conditioning' => '❄️ Aer condiționat',
                'central_heating' => '🔥 Încălzire centrală',
                'climate_control' => '🌡️ Climat controlat',
                'pro_ventilation' => '🌬️ Ventilație profesională',
                'heated_terrace' => '🏖️ Terasă încălzită',
                'retractable_roof' => '☔ Acoperiș retractabil',
                'covered_outdoor' => '🌂 Zonă acoperită outdoor',
            ],
        ],
        'technology' => [
            'label' => '📶 Tehnologie & Conectivitate',
            'items' => [
                'free_wifi' => '📶 WiFi gratuit',
                'highspeed_wifi' => '📶 WiFi high-speed',
                'public_outlets' => '🔌 Prize electrice publice',
                'phone_charging' => '🔋 Stații încărcare telefoane',
                'venue_app' => '📱 Aplicație dedicată locație',
                'digital_tickets' => '🎟️ Scanare bilete digitale',
                'cashless_nfc' => '💳 Sistem cashless / brățări NFC',
                'digital_checkin' => '📍 Check-in digital',
            ],
        ],
        'security' => [
            'label' => '🛡️ Securitate & Siguranță',
            'items' => [
                'security_24_7' => '🛡️ Securitate 24/7',
                'security_staff' => '👮 Personal securitate',
                'cctv' => '📹 Supraveghere CCTV',
                'alarm_system' => '🚨 Sistem alarmă',
                'fire_system' => '🧯 Sistem antiincendiu',
                'sprinklers' => '🚿 Sprinklere',
                'emergency_exits' => '🚪 Ieșiri de urgență',
                'first_aid' => '⛑️ Punct prim ajutor',
                'medical_staff' => '🏥 Personal medical',
                'ambulance_standby' => '🚑 Ambulanță standby',
                'access_control' => '🔍 Control acces / Filtrare',
                'metal_detectors' => '🚫 Detectoare metale',
            ],
        ],
        'outdoor_festival' => [
            'label' => '🎪 Outdoor & Festival',
            'items' => [
                'camping_zone' => '🏕️ Zonă camping',
                'glamping' => '⛺ Glamping',
                'outdoor_showers' => '🚿 Dușuri outdoor',
                'eco_toilets' => '🚽 Toalete ecologice',
                'green_spaces' => '🌳 Spații verzi',
                'shade_zones' => '🌴 Zone umbră',
                'relaxation_zone' => '🏖️ Zonă relaxare',
                'water_points' => '💧 Puncte apă potabilă',
                'sunscreen_points' => '🧴 Puncte protecție solară',
                'ferris_wheel' => '🎡 Roată panoramică',
                'carousel' => '🎠 Carusel / Rides',
                'activity_zones' => '🎯 Zone activități',
            ],
        ],
        'conference' => [
            'label' => '🏢 Conferințe & Business',
            'items' => [
                'conference_system' => '🎤 Sistem conferință',
                'presentation_screens' => '🖥️ Ecrane prezentare',
                'flipchart' => '📊 Flipchart / Whiteboard',
                'simultaneous_translation' => '🎧 Traducere simultană',
                'breakout_rooms' => '📝 Săli breakout',
                'coffee_break_area' => '☕ Coffee break area',
                'business_center' => '🖨️ Business center',
                'print_services' => '📠 Servicii printare',
                'meeting_rooms' => '💼 Săli de ședințe',
                'networking_zone' => '🤝 Zonă networking',
                'photo_booth' => '📸 Photo booth',
                'exhibition_zone' => '🎁 Zonă expoziții / Standuri',
            ],
        ],
        'family' => [
            'label' => '👨‍👩‍👧‍👦 Familie & Copii',
            'items' => [
                'baby_care_room' => '👶 Cameră bebeluși',
                'kids_play_zone' => '🧒 Zonă joacă copii',
                'kids_activities' => '🎨 Activități pentru copii',
                'kids_supervision' => '👀 Supraveghere copii',
                'bottle_prep' => '🍼 Preparare biberon',
                'changing_table' => '🚼 Înfășător',
                'kids_corner' => '🧸 Kids corner',
            ],
        ],
        'accessibility' => [
            'label' => '♿ Accesibilitate',
            'items' => [
                'access_ramps' => '♿ Rampe acces',
                'wheelchair_spaces' => '🦽 Locuri scaune rulante',
                'blind_guidance' => '🦯 Ghidaj pentru nevăzători',
                'hearing_loop' => '👂 Sistem auditiv (hearing loop)',
                'sign_language' => '🤟 Interpret limbaj semne',
                'live_subtitles' => '🔤 Subtitrare live',
                'guide_dogs' => '🐕‍🦺 Acces câini ghid',
                'disabled_parking' => '🅿️ Parcare persoane cu dizabilități',
            ],
        ],
        'premium' => [
            'label' => '🌟 Premium & VIP',
            'items' => [
                'vip_lounge' => '👑 Lounge VIP',
                'vip_open_bar' => '🥂 Open bar VIP',
                'bottle_service' => '🍾 Bottle service',
                'private_booths' => '🛋️ Separeuri private',
                'meet_greet_room' => '🎭 Meet & Greet room',
                'valet_parking' => '🚗 Valet parking',
                'private_entrance' => '🚪 Intrare privată',
                'concierge' => '🛎️ Concierge service',
                'gift_shop' => '🎁 Gift shop / Merch',
            ],
        ],
        'eco' => [
            'label' => '🌿 Eco & Sustenabilitate',
            'items' => [
                'recycling' => '♻️ Reciclare selectivă',
                'green_energy' => '🌱 Energie verde',
                'solar_panels' => '☀️ Panouri solare',
                'water_recycling' => '💧 Sistem reciclare apă',
                'reusable_cups' => '🥤 Pahare reutilizabile',
                'zero_plastic' => '🚫 Zero plastic single-use',
                'carbon_neutral' => '🌍 Carbon neutral',
            ],
        ],
    ];

    protected $fillable = [
        'tenant_id','marketplace_client_id','venue_type_id','venue_tag','facilities','name','slug','address','city','state','country',
        'website_url','phone','phone2','email','email2',
        'facebook_url','instagram_url','tiktok_url',
        'image_url','video_type','video_url','gallery',
        'capacity','capacity_total','capacity_standing','capacity_seated',
        'lat','lng','google_maps_url','established_at','description','schedule','meta',
        'is_partner','partner_notes','is_featured','has_historical_monument_tax',
        'timezone','open_hours','general_rules','child_rules','accepted_payment','venue_conditions',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'meta' => 'array',
        'gallery' => 'array',
        'facilities' => 'array',
        'established_at' => 'date',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_partner' => 'boolean',
        'is_featured' => 'boolean',
        'has_historical_monument_tax' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Marketplace clients that have this venue in their partner list (many-to-many)
     */
    public function marketplaceClients(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceClient::class, 'marketplace_venue_partners')
            ->withPivot('is_partner', 'partner_notes')
            ->withTimestamps();
    }

    /**
     * Check if this venue is linked to a specific marketplace client
     */
    public function isInMarketplace(int $marketplaceClientId): bool
    {
        return $this->marketplaceClients()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->exists();
    }

    /**
     * Scope: venues that are confirmed partners of the given marketplace client,
     * either via the direct FK (venues.marketplace_client_id + is_partner=true)
     * or via the marketplace_venue_partners pivot (with pivot is_partner=true).
     */
    public function scopePartnerOfMarketplace($query, int $marketplaceClientId)
    {
        return $query->where(function ($q) use ($marketplaceClientId) {
            $q->where(function ($direct) use ($marketplaceClientId) {
                $direct->where('marketplace_client_id', $marketplaceClientId)
                       ->where('is_partner', true);
            })->orWhereHas('marketplaceClients', function ($pivot) use ($marketplaceClientId) {
                $pivot->where('marketplace_clients.id', $marketplaceClientId)
                      ->where('marketplace_venue_partners.is_partner', true);
            });
        });
    }

    public function venueType(): BelongsTo
    {
        return $this->belongsTo(VenueType::class);
    }

    /**
     * Core venue types (many-to-many, max 5)
     */
    public function venueTypes(): BelongsToMany
    {
        return $this->belongsToMany(VenueType::class, 'venue_type_venue')
            ->withTimestamps();
    }

    /**
     * Core venue categories (many-to-many, max 3)
     */
    public function coreCategories(): BelongsToMany
    {
        return $this->belongsToMany(VenueCategory::class, 'venue_category_venue')
            ->withTimestamps();
    }

    public function venueCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceVenueCategory::class,
            'marketplace_venue_category_venue',
            'venue_id',
            'marketplace_venue_category_id'
        )->withPivot('sort_order')->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(\App\Models\Event::class);
    }

    public function marketplaceEvents(): HasMany
    {
        return $this->hasMany(\App\Models\MarketplaceEvent::class);
    }

    public function seatingLayouts(): HasMany
    {
        return $this->hasMany(\App\Models\Seating\SeatingLayout::class);
    }

    public function gates(): HasMany
    {
        return $this->hasMany(VenueGate::class)->orderBy('sort_order');
    }

    /**
     * Get tag info (label and icon) for current venue_tag
     */
    public function getTagInfo(): ?array
    {
        if (!$this->venue_tag) {
            return null;
        }
        return self::TAG_OPTIONS[$this->venue_tag] ?? null;
    }

    /**
     * Get tag label with icon
     */
    public function getTagLabelWithIcon(): ?string
    {
        $info = $this->getTagInfo();
        if (!$info) {
            return null;
        }
        return $info['icon'] . ' ' . $info['label'];
    }

    /**
     * Get dropdown options for venue tag select
     */
    public static function getTagSelectOptions(): array
    {
        $options = [];
        foreach (self::TAG_OPTIONS as $key => $info) {
            $options[$key] = $info['icon'] . ' ' . $info['label'];
        }
        return $options;
    }

    /**
     * Get facilities with their labels for display
     */
    public function getFacilitiesWithLabels(): array
    {
        if (empty($this->facilities)) {
            return [];
        }

        $result = [];
        foreach (self::FACILITIES as $categoryKey => $category) {
            foreach ($category['items'] as $key => $label) {
                if (in_array($key, $this->facilities)) {
                    $result[] = [
                        'key' => $key,
                        'label' => $label,
                        'category' => $category['label'],
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Get all facilities as flat array for CheckboxList options
     */
    public static function getFacilitiesOptions(): array
    {
        $options = [];
        foreach (self::FACILITIES as $category) {
            foreach ($category['items'] as $key => $label) {
                $options[$key] = $label;
            }
        }
        return $options;
    }

    // Route key removed - Filament admin uses ID via getRecordRouteKeyName
    // Public routes use explicit slug binding: Route::get('/venue/{venue:slug}', ...)

    protected static function booted(): void
    {
        static::creating(function (self $venue) {
            if (blank($venue->slug)) {
                // Get English name from translatable field
                $name = is_array($venue->name) ? ($venue->name['en'] ?? '') : $venue->name;
                if (filled($name)) {
                    $venue->slug = static::uniqueSlug(Str::slug($name));
                }
            }
        });

        // nu schimbăm slug-ul la update automat (ca să nu rupem URL-urile).
    }

    protected static function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'venue';
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}
