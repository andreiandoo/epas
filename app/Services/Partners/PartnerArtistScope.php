<?php

namespace App\Services\Partners;

use App\Models\Artist;
use App\Models\MarketplaceClient;
use Illuminate\Database\Eloquent\Builder;

/**
 * Artists a media partner may see and write about for a marketplace: its partner
 * artists plus any artist booked on one of its published events.
 */
class PartnerArtistScope
{
    public static function query(MarketplaceClient $client): Builder
    {
        return Artist::query()
            ->where('artists.is_active', true)
            ->where(fn ($q) => $q
                ->whereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('marketplace_artist_partners')
                    ->whereColumn('marketplace_artist_partners.artist_id', 'artists.id')
                    ->where('marketplace_artist_partners.marketplace_client_id', $client->id))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('event_artist')
                    ->join('events', 'events.id', '=', 'event_artist.event_id')
                    ->whereColumn('event_artist.artist_id', 'artists.id')
                    ->where('events.marketplace_client_id', $client->id)
                    ->where('events.is_published', true)));
    }
}
