<?php

namespace App\Filament\Exports;

use App\Models\Venue;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class VenueExporter extends Exporter
{
    protected static ?string $model = Venue::class;

    public static function getColumns(): array
    {
        return [
            // Core
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('slug')->label('Slug'),

            // Translatable Name
            ExportColumn::make('name_ro')
                ->label('Name (RO)')
                ->state(fn (Venue $record) => $record->getTranslation('name', 'ro') ?? ''),
            ExportColumn::make('name_en')
                ->label('Name (EN)')
                ->state(fn (Venue $record) => $record->getTranslation('name', 'en') ?? ''),

            // Tenant
            ExportColumn::make('tenant_name')
                ->label('Tenant')
                ->state(fn (Venue $record) => $record->tenant?->name ?? ''),

            // Location
            ExportColumn::make('address')->label('Address'),
            ExportColumn::make('city')->label('City'),
            ExportColumn::make('state')->label('State'),
            ExportColumn::make('country')->label('Country'),
            ExportColumn::make('lat')->label('Latitude'),
            ExportColumn::make('lng')->label('Longitude'),
            ExportColumn::make('google_maps_url')->label('Google Maps URL'),

            // Contact
            ExportColumn::make('phone')->label('Phone'),
            ExportColumn::make('phone2')->label('Phone 2'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('email2')->label('Email 2'),
            ExportColumn::make('website_url')->label('Website'),

            // Social
            ExportColumn::make('facebook_url')->label('Facebook URL'),
            ExportColumn::make('instagram_url')->label('Instagram URL'),
            ExportColumn::make('tiktok_url')->label('TikTok URL'),

            // Media
            ExportColumn::make('image_url')->label('Main Image'),
            ExportColumn::make('video_type')->label('Video Type'),
            ExportColumn::make('video_url')->label('Video URL'),
            ExportColumn::make('gallery_list')
                ->label('Gallery Images')
                ->state(fn (Venue $record) => collect($record->gallery ?? [])->implode(' | ')),

            // Capacity
            ExportColumn::make('capacity_total')->label('Capacity Total'),
            ExportColumn::make('capacity_standing')->label('Capacity Standing'),
            ExportColumn::make('capacity_seated')->label('Capacity Seated'),
            ExportColumn::make('capacity')->label('Capacity (Legacy)'),

            // Venue Details
            ExportColumn::make('venue_tag')->label('Venue Tag'),
            ExportColumn::make('timezone')->label('Timezone'),
            ExportColumn::make('open_hours')->label('Open Hours'),
            ExportColumn::make('general_rules')->label('General Rules'),
            ExportColumn::make('child_rules')->label('Child Rules'),
            ExportColumn::make('accepted_payment')->label('Accepted Payment'),
            ExportColumn::make('established_at')->label('Established At'),
            ExportColumn::make('has_historical_monument_tax')
                ->label('Historical Monument Tax')
                ->state(fn (Venue $record) => $record->has_historical_monument_tax ? 'Yes' : 'No'),

            // Facilities
            ExportColumn::make('facilities_list')
                ->label('Facilities')
                ->state(fn (Venue $record) => collect($record->facilities ?? [])->implode(', ')),

            // Translatable Description
            ExportColumn::make('description_ro')
                ->label('Description (RO)')
                ->state(fn (Venue $record) => strip_tags($record->getTranslation('description', 'ro') ?? '')),
            ExportColumn::make('description_en')
                ->label('Description (EN)')
                ->state(fn (Venue $record) => strip_tags($record->getTranslation('description', 'en') ?? '')),

            // Relationships
            ExportColumn::make('venue_types_list')
                ->label('Venue Types')
                ->state(fn (Venue $record) => $record->venueTypes->pluck('slug')->implode(', ')),
            ExportColumn::make('venue_categories_list')
                ->label('Venue Categories')
                ->state(fn (Venue $record) => $record->coreCategories->pluck('slug')->implode(', ')),
            ExportColumn::make('events_count')
                ->label('Total Events'),

            // Marketplace
            ExportColumn::make('marketplace_client_id')->label('Marketplace Client ID'),
            ExportColumn::make('is_partner')
                ->label('Is Partner')
                ->state(fn (Venue $record) => $record->is_partner ? 'Yes' : 'No'),
            ExportColumn::make('partner_notes')->label('Partner Notes'),
            ExportColumn::make('is_featured')
                ->label('Is Featured')
                ->state(fn (Venue $record) => $record->is_featured ? 'Yes' : 'No'),

            // Legacy JSON
            ExportColumn::make('schedule_json')
                ->label('Schedule (Legacy)')
                ->state(fn (Venue $record) => json_encode($record->schedule ?? null)),
            ExportColumn::make('meta_json')
                ->label('Meta (Legacy)')
                ->state(fn (Venue $record) => json_encode($record->meta ?? null)),

            // Timestamps
            ExportColumn::make('created_at')->label('Created At'),
            ExportColumn::make('updated_at')->label('Updated At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export locatii finalizat: ' . number_format($export->successful_rows) . ' randuri exportate.';

        if ($failedCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedCount) . ' randuri esuate.';
        }

        return $body;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->with(['tenant', 'venueTypes', 'coreCategories'])
            ->withCount('events');
    }
}
