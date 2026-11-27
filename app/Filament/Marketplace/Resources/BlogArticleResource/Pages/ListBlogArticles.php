<?php

namespace App\Filament\Marketplace\Resources\BlogArticleResource\Pages;

use App\Filament\Marketplace\Resources\BlogArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogArticles extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = BlogArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
