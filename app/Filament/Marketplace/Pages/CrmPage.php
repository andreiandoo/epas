<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CrmPage extends Page
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    use HasMarketplaceContext;

    // Pagină copiată din panoul tenant și neterminată pentru marketplace (view-ul există doar la tenant):
    // o închidem ca să răspundă 403, nu eroare 500, și să nu apară în meniu.
    public static function canAccess(): bool
    {
        return false;
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'CRM';
    protected static \UnitEnum|string|null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'crm';
    protected string $view = 'filament.marketplace.pages.crm';

    public function getHeading(): string
    {
        return '';
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('crm');
    }

}
