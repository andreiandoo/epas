<?php

namespace App\Filament\Marketplace\Resources\ChatOperatorScheduleResource\Pages;

use App\Filament\Marketplace\Resources\ChatOperatorScheduleResource;
use App\Models\Chat\ChatOperatorSchedule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateChatOperatorSchedule extends CreateRecord
{
    protected static string $resource = ChatOperatorScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
        return $data;
    }

    /**
     * Create one schedule row per selected weekday. Filament expects a single
     * model back (used for the redirect), so we return the first row created.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $days = $data['days'] ?? [];
        unset($data['days']);

        // Fallback: if somehow no multi-day list came through, use day_of_week.
        if (empty($days) && isset($data['day_of_week'])) {
            $days = [$data['day_of_week']];
        }
        $days = array_values(array_unique(array_map('intval', (array) $days)));

        $first = null;
        foreach ($days as $day) {
            $row = ChatOperatorSchedule::create(array_merge($data, ['day_of_week' => $day]));
            $first ??= $row;
        }

        // Defensive: never return null (empty selection is blocked by validation).
        return $first ?? ChatOperatorSchedule::create(array_merge($data, ['day_of_week' => 1]));
    }
}
