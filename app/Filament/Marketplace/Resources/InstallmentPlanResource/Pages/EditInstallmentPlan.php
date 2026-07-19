<?php

namespace App\Filament\Marketplace\Resources\InstallmentPlanResource\Pages;

use App\Filament\Marketplace\Resources\InstallmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentPlan extends EditRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['plan_type'] ?? null) === 'bnpl_single') {
            $data['number_of_installments'] = 1;
        } elseif (($data['schedule_type'] ?? null) === 'custom' && is_array($data['custom_schedule'] ?? null)) {
            $data['number_of_installments'] = count($data['custom_schedule']);
        }
        return $data;
    }
}
