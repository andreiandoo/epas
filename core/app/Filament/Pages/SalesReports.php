<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;

class SalesReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Reports';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.sales-reports';
}
