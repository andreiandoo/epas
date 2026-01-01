<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Doc;
use App\Models\DocCategory;
use BackedEnum;
use Filament\Pages\Page;

class Documentation extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Documentation';
    protected static \UnitEnum|string|null $navigationGroup = 'Help';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.documentation';

    public function getTitle(): string
    {
        return 'Documentation';
    }

    public function getCategories()
    {
        return DocCategory::with(['docs' => function ($query) {
            $query->where('is_public', true)
                ->where('status', 'published')
                ->orderBy('order');
        }])
        ->where('is_public', true)
        ->orderBy('order')
        ->get();
    }

    public function getFeaturedDocs()
    {
        return Doc::where('is_public', true)
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('order')
            ->limit(6)
            ->get();
    }
}
