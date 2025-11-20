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
                'category' => 'Public Data API',
                'description' => 'Public endpoints secured with API key authentication',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/stats',
                        'description' => 'Get overall statistics (counts)',
                        'response' => '{ events, venues, artists, tenants }',
                    ],
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
                        'response' => '{ venue object }',
                    ],
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
                        'response' => '{ artist object }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/stats',
                        'description' => 'Get artist stats with YouTube/Spotify data',
                        'response' => '{ id, name, slug, social, followers, youtube: { channel, videos }, spotify: { artist, top_tracks, embed_html }, kpis }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/youtube',
                        'description' => 'Get artist YouTube statistics',
                        'response' => '{ channel: { subscribers, views, videos }, videos: [...], recent_videos: [...] }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/artists/{slug}/spotify',
                        'description' => 'Get artist Spotify data',
                        'response' => '{ artist: { name, genres, popularity, followers }, top_tracks: [...], albums: [...], related_artists: [...], embed_html }',
                    ],
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
                        'response' => '{ id, name, public_name, slug, city, country }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/events',
                        'description' => 'List events (max 100)',
                        'response' => '[{ id, title, slug, start_date, end_date, venue, tenant }]',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/v1/public/events/{slug}',
                        'description' => 'Get single event details',
                        'response' => '{ event with venue, artists, tenant }',
                    ],
                ],
            ],
            [
                'category' => 'Authentication',
                'description' => 'How to authenticate API requests',
                'endpoints' => [
                    [
                        'method' => 'HEADER',
                        'path' => 'X-API-Key: your_api_key',
                        'description' => 'Send API key in header (recommended)',
                        'response' => '',
                    ],
                    [
                        'method' => 'QUERY',
                        'path' => '?api_key=your_api_key',
                        'description' => 'Send API key as query parameter',
                        'response' => '',
                    ],
                ],
            ],
        ];
    }
}
