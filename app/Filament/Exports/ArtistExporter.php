<?php

namespace App\Filament\Exports;

use App\Models\Artist;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class ArtistExporter extends Exporter
{
    protected static ?string $model = Artist::class;

    public static function getColumns(): array
    {
        return [
            // Core
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('slug')->label('Slug'),
            ExportColumn::make('letter')->label('Letter'),
            ExportColumn::make('is_active')
                ->label('Active')
                ->state(fn (Artist $record) => $record->is_active ? 'Yes' : 'No'),

            // Contact
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('phone')->label('Phone'),
            ExportColumn::make('website')->label('Website'),

            // Location
            ExportColumn::make('country')->label('Country'),
            ExportColumn::make('city')->label('City'),

            // Social URLs
            ExportColumn::make('facebook_url')->label('Facebook URL'),
            ExportColumn::make('instagram_url')->label('Instagram URL'),
            ExportColumn::make('tiktok_url')->label('TikTok URL'),
            ExportColumn::make('youtube_url')->label('YouTube URL'),
            ExportColumn::make('spotify_url')->label('Spotify URL'),
            ExportColumn::make('twitter_url')->label('Twitter URL'),
            ExportColumn::make('wiki_url')->label('Wikipedia URL'),
            ExportColumn::make('lastfm_url')->label('Last.fm URL'),
            ExportColumn::make('itunes_url')->label('iTunes URL'),
            ExportColumn::make('musicbrainz_url')->label('MusicBrainz URL'),

            // Social IDs
            ExportColumn::make('youtube_id')->label('YouTube ID'),
            ExportColumn::make('spotify_id')->label('Spotify ID'),

            // Social Stats
            ExportColumn::make('followers_facebook')->label('Facebook Followers'),
            ExportColumn::make('followers_instagram')->label('Instagram Followers'),
            ExportColumn::make('followers_tiktok')->label('TikTok Followers'),
            ExportColumn::make('followers_youtube')->label('YouTube Subscribers'),
            ExportColumn::make('youtube_total_views')->label('YouTube Total Views'),
            ExportColumn::make('youtube_total_likes')->label('YouTube Total Likes'),
            ExportColumn::make('spotify_monthly_listeners')->label('Spotify Monthly Listeners'),
            ExportColumn::make('spotify_popularity')->label('Spotify Popularity'),
            ExportColumn::make('twitter_followers')->label('Twitter Followers'),
            ExportColumn::make('social_stats_updated_at')->label('Stats Updated At'),

            // Media
            ExportColumn::make('main_image_url')->label('Main Image URL'),
            ExportColumn::make('logo_url')->label('Logo URL'),
            ExportColumn::make('portrait_url')->label('Portrait URL'),

            // Translatable Bio
            ExportColumn::make('bio_html_ro')
                ->label('Bio (RO)')
                ->state(fn (Artist $record) => strip_tags($record->getTranslation('bio_html', 'ro') ?? '')),
            ExportColumn::make('bio_html_en')
                ->label('Bio (EN)')
                ->state(fn (Artist $record) => strip_tags($record->getTranslation('bio_html', 'en') ?? '')),

            // YouTube Videos
            ExportColumn::make('youtube_videos_list')
                ->label('YouTube Videos')
                ->state(function (Artist $record) {
                    $videos = $record->youtube_videos ?? [];
                    return collect($videos)->pluck('url')->filter()->implode(' | ');
                }),

            // Manager
            ExportColumn::make('manager_first_name')->label('Manager First Name'),
            ExportColumn::make('manager_last_name')->label('Manager Last Name'),
            ExportColumn::make('manager_email')->label('Manager Email'),
            ExportColumn::make('manager_phone')->label('Manager Phone'),
            ExportColumn::make('manager_website')->label('Manager Website'),

            // Agent
            ExportColumn::make('agent_first_name')->label('Agent First Name'),
            ExportColumn::make('agent_last_name')->label('Agent Last Name'),
            ExportColumn::make('agent_email')->label('Agent Email'),
            ExportColumn::make('agent_phone')->label('Agent Phone'),
            ExportColumn::make('agent_website')->label('Agent Website'),

            // Booking Agency (flattened JSON)
            ExportColumn::make('booking_agency_name')
                ->label('Booking Agency Name')
                ->state(fn (Artist $record) => $record->booking_agency['name'] ?? ''),
            ExportColumn::make('booking_agency_email')
                ->label('Booking Agency Email')
                ->state(fn (Artist $record) => $record->booking_agency['email'] ?? ''),
            ExportColumn::make('booking_agency_phone')
                ->label('Booking Agency Phone')
                ->state(fn (Artist $record) => $record->booking_agency['phone'] ?? ''),
            ExportColumn::make('booking_agency_website')
                ->label('Booking Agency Website')
                ->state(fn (Artist $record) => $record->booking_agency['website'] ?? ''),

            // Relationships
            ExportColumn::make('artist_types_list')
                ->label('Artist Types')
                ->state(fn (Artist $record) => $record->artistTypes->pluck('slug')->implode(', ')),
            ExportColumn::make('artist_genres_list')
                ->label('Artist Genres')
                ->state(fn (Artist $record) => $record->artistGenres->pluck('slug')->implode(', ')),
            ExportColumn::make('events_count')
                ->label('Total Events'),

            // Marketplace
            ExportColumn::make('marketplace_client_id')->label('Marketplace Client ID'),
            ExportColumn::make('is_partner')
                ->label('Is Partner')
                ->state(fn (Artist $record) => $record->is_partner ? 'Yes' : 'No'),
            ExportColumn::make('partner_notes')->label('Partner Notes'),
            ExportColumn::make('is_featured')
                ->label('Is Featured')
                ->state(fn (Artist $record) => $record->is_featured ? 'Yes' : 'No'),

            // Timestamps
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export artisti finalizat: ' . number_format($export->successful_rows) . ' randuri exportate.';

        if ($failedCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedCount) . ' randuri esuate.';
        }

        return $body;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->with(['artistTypes', 'artistGenres'])
            ->withCount('events');
    }
}
