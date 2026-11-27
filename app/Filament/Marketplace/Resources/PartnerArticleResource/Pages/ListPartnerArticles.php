<?php

namespace App\Filament\Marketplace\Resources\PartnerArticleResource\Pages;

use App\Filament\Marketplace\Resources\PartnerArticleResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerArticles extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = PartnerArticleResource::class;
}
