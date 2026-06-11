<?php

namespace App\Filament\Tenant\Resources\BlogCategoryResource\Pages;

use App\Filament\Tenant\Resources\BlogCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogCategory extends CreateRecord
{
    protected static string $resource = BlogCategoryResource::class;
}
