<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ApiKnowledgeBase extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'API KB';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.api-knowledge-base';

    public function getTitle(): string
    {
        return 'API Knowledge Base';
    }

    public function getEndpoints(): array
    {
        return [
            [
                'category' => 'Authentication',
                'description' => 'How to connect to the API',
                'endpoints' => [
                    [
                        'method' => 'HEADER',
                        'path' => 'X-API-Key: pk_your_api_key',
                        'description' => 'Send API key in header (recommended)',
                        'response' => '',
                    ],
                    [
                        'method' => 'QUERY',
                        'path' => '?api_key=pk_your_api_key',
                        'description' => 'Send API key as query parameter',
                        'response' => '',
                    ],
                    [
                        'method' => 'INFO',
                        'path' => 'Create API key in Settings > API Keys',
                        'description' => 'Each key has a public key (pk_) and secret key (sk_) for HMAC signing',
                        'response' => '',
                    ],
                ],
            ],
            [
                'category' => 'HMAC Signature (Optional)',
                'description' => 'Enhanced security with request signing - prevents replay attacks',
                'endpoints' => [
                    [
                        'method' => 'HEADER',
                        'path' => 'X-Timestamp: unix_timestamp',
                        'description' => 'Current Unix timestamp (must be within 5 minutes)',
                        'response' => '',
                    ],
                    [
                        'method' => 'HEADER',
                        'path' => 'X-Signature: hmac_sha256',
                        'description' => 'HMAC-SHA256 of (timestamp + request_path) using secret_key',
                        'response' => '',
                    ],
                    [
                        'method' => 'CODE',
                        'path' => 'signature = hash_hmac("sha256", timestamp + path, secret_key)',
                        'description' => 'PHP: hash_hmac("sha256", "1700000000api/v1/public/stats", "sk_xxx")',
                        'response' => '',
                    ],
                ],
            ],
            [
                'category' => 'Statistics',
                'description' => 'General platform statistics',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/stats',
                        'description' => 'Get overall statistics (counts)',
                        'response' => '{ events, venues, artists, tenants }',
                    ],
                ],
            ],
            [
                'category' => 'Artists',
                'description' => 'Artist data including social media stats from YouTube and Spotify',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists',
                        'description' => 'List all artists',
                        'response' => '[{ id, name, slug, country, bio }]',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}',
                        'description' => 'Get single artist details',
                        'response' => '{ id, name, slug, country, bio, social_links, images }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/stats',
                        'description' => 'Get combined stats with YouTube/Spotify data',
                        'response' => '{ social, followers, youtube: { channel, videos }, spotify: { artist, top_tracks }, kpis }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/youtube',
                        'description' => 'Get YouTube channel statistics',
                        'response' => '{ channel: { subscribers, views, videos }, videos: [...], recent_videos: [...] }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/spotify',
                        'description' => 'Get Spotify artist data',
                        'response' => '{ artist: { genres, popularity, followers }, top_tracks, albums, related_artists }',
                    ],
                ],
            ],
            [
                'category' => 'Venues',
                'description' => 'Venue locations and capacity information',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/venues',
                        'description' => 'List all venues',
                        'response' => '[{ id, name, slug, city, country, capacity, address, latitude, longitude }]',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/venues/{slug}',
                        'description' => 'Get single venue details',
                        'response' => '{ id, name, slug, city, country, capacity, address, latitude, longitude, description }',
                    ],
                ],
            ],
            [
                'category' => 'Events',
                'description' => 'Event listings with venue and artist associations',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/events',
                        'description' => 'List events (max 100)',
                        'response' => '[{ id, title, slug, start_date, end_date, venue, tenant }]',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/events/{slug}',
                        'description' => 'Get single event with full details',
                        'response' => '{ id, title, slug, dates, venue, artists, tenant, description }',
                    ],
                ],
            ],
            [
                'category' => 'Tenants',
                'description' => 'Event organizers / tenant information',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/tenants',
                        'description' => 'List all active tenants',
                        'response' => '[{ id, name, public_name, slug, city, country }]',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/tenants/{slug}',
                        'description' => 'Get single tenant details',
                        'response' => '{ id, name, public_name, slug, city, country, contact_info }',
                    ],
                ],
            ],
        ];
    }
}
