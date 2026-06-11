<?php

namespace App\Filament\Tenant\Resources\TenantTeamMemberResource\Pages;

use App\Filament\Tenant\Resources\TenantTeamMemberResource;
use App\Models\Leisure\TenantTeamMember;
use App\Models\Leisure\TenantTeamMemberShift;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenantTeamMember extends EditRecord
{
    protected static string $resource = TenantTeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulkAddShifts')
                ->label('Adaugă schimburi pe perioadă')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->modalHeading('Generează schimburi pentru o perioadă')
                ->modalDescription('Selectează interval + zile + ore. Generăm un schimb pentru fiecare zi care matchuiește.')
                ->modalSubmitActionLabel('Generează')
                ->form([
                    Forms\Components\DatePicker::make('date_start')
                        ->label('De la')
                        ->required()
                        ->default(now()->toDateString()),
                    Forms\Components\DatePicker::make('date_end')
                        ->label('Până la')
                        ->required()
                        ->default(now()->addMonths(3)->toDateString())
                        ->helperText('Inclusiv. Max 365 zile.'),
                    Forms\Components\CheckboxList::make('weekdays')
                        ->label('Zile aplicabile')
                        ->options([
                            1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi',
                            5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică',
                        ])
                        ->columns(7)
                        ->default([1, 2, 3, 4, 5])
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TimePicker::make('start_time')
                        ->label('Ora start')
                        ->seconds(false)
                        ->default('09:00')
                        ->required(),
                    Forms\Components\TimePicker::make('end_time')
                        ->label('Ora sfârșit')
                        ->seconds(false)
                        ->default('17:00')
                        ->required(),
                    Forms\Components\Select::make('position')
                        ->label('Poziție')
                        ->options(TenantTeamMember::LEISURE_ROLES)
                        ->placeholder('Folosește rolul implicit'),
                    Forms\Components\TextInput::make('location')
                        ->label('Locație / Gate')
                        ->placeholder('ex: Intrare principală'),
                    Forms\Components\TagsInput::make('excluded_dates')
                        ->label('Date excluse')
                        ->placeholder('YYYY-MM-DD')
                        ->helperText('Tastează datele pe care vrei să le sari (sărbători, concedii) — format YYYY-MM-DD, Enter după fiecare.')
                        ->columnSpanFull(),
                    Forms\Components\Radio::make('on_conflict')
                        ->label('Dacă există deja un schimb pe acea zi')
                        ->options([
                            'skip' => 'Sari peste (default)',
                            'replace' => 'Înlocuiește schimburile existente',
                            'add' => 'Adaugă în plus',
                        ])
                        ->default('skip')
                        ->required()
                        ->inline(false),
                ])
                ->action(function (array $data) {
                    $member = $this->getRecord();
                    if (! $member) return;

                    $start = CarbonImmutable::parse($data['date_start']);
                    $end = CarbonImmutable::parse($data['date_end']);
                    if ($end->diffInDays($start) > 365) {
                        Notification::make()->danger()->title('Interval prea lung (max 365 zile)')->send();
                        return;
                    }
                    if ($end->lessThan($start)) {
                        Notification::make()->danger()->title('Data finală trebuie să fie după data inițială')->send();
                        return;
                    }
                    $weekdays = array_map('intval', $data['weekdays'] ?? []);
                    $excluded = collect($data['excluded_dates'] ?? [])
                        ->map(fn ($d) => trim((string) $d))
                        ->filter()
                        ->all();

                    $created = 0;
                    $skipped = 0;
                    $replaced = 0;

                    for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
                        if (! in_array((int) $d->format('N'), $weekdays, true)) {
                            continue;
                        }
                        if (in_array($d->toDateString(), $excluded, true)) {
                            continue;
                        }

                        $existingCount = TenantTeamMemberShift::query()
                            ->where('tenant_team_member_id', $member->id)
                            ->where('shift_date', $d->toDateString())
                            ->count();

                        if ($existingCount > 0) {
                            if (($data['on_conflict'] ?? 'skip') === 'skip') {
                                $skipped++;
                                continue;
                            }
                            if (($data['on_conflict'] ?? 'skip') === 'replace') {
                                TenantTeamMemberShift::query()
                                    ->where('tenant_team_member_id', $member->id)
                                    ->where('shift_date', $d->toDateString())
                                    ->delete();
                                $replaced += $existingCount;
                            }
                            // on_conflict=add → just create another row
                        }

                        TenantTeamMemberShift::create([
                            'tenant_id' => $member->tenant_id,
                            'tenant_team_member_id' => $member->id,
                            'shift_date' => $d->toDateString(),
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],
                            'position' => $data['position'] ?? null,
                            'location' => $data['location'] ?? null,
                        ]);
                        $created++;
                    }

                    Notification::make()
                        ->success()
                        ->title("Schimburi generate: {$created}")
                        ->body(($skipped > 0 ? "Sărite (conflict): {$skipped}. " : '') . ($replaced > 0 ? "Înlocuite: {$replaced}." : ''))
                        ->send();

                    $this->refreshFormData(['shifts']);
                }),

            Actions\Action::make('clearShifts')
                ->label('Șterge toate schimburile')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Șterge toate schimburile acestui operator')
                ->action(function () {
                    $member = $this->getRecord();
                    if (! $member) return;
                    $count = TenantTeamMemberShift::where('tenant_team_member_id', $member->id)->delete();
                    Notification::make()->success()->title("Șterse {$count} schimburi")->send();
                    $this->refreshFormData(['shifts']);
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sync user.name update back to the linked User record.
        $userData = $this->data['user'] ?? [];
        $newName = $userData['name'] ?? null;
        if ($newName && $this->record->user && $this->record->user->name !== $newName) {
            $this->record->user->update(['name' => $newName]);
        }

        // Optional password reset.
        $newPassword = $this->data['initial_password'] ?? null;
        if ($newPassword && $this->record->user) {
            $this->record->user->update(['password' => \Illuminate\Support\Facades\Hash::make($newPassword)]);
        }

        return $data;
    }
}
