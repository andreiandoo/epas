<?php

namespace App\Filament\Marketplace\Resources\ArtistAccountResource\Pages;

use App\Filament\Marketplace\Resources\ArtistAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListArtistAccounts extends ListRecords
{
    protected static string $resource = ArtistAccountResource::class;

    public function getHeading(): string|Htmlable
    {
        $pendingCount = number_format(
            static::getResource()::getEloquentQuery()->where('status', 'pending')->count()
        );
        $totalCount = number_format(static::getResource()::getEloquentQuery()->count());

        return new HtmlString(
            'Conturi Artist '
            . '<span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">' . $totalCount . '</span> '
            . ($pendingCount > 0
                ? '<span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-sm font-medium text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">' . $pendingCount . ' în review</span>'
                : '')
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Cont nou')
                ->icon('heroicon-m-plus'),
        ];
    }
}
