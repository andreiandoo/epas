<?php

namespace App\Filament\Marketplace\Resources\BlogArticleResource\Pages;

use App\Filament\Marketplace\Resources\BlogArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogArticle extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = BlogArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
