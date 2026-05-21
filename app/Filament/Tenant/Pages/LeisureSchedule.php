<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Leisure\TenantTeamMember;
use App\Models\Leisure\TenantTeamMemberShift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class LeisureSchedule extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-date-range';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 12;
    protected static ?string $title = 'Pontaj';
    protected static ?string $navigationLabel = 'Pontaj';
    protected static ?string $slug = 'leisure/schedule';
    protected string $view = 'filament.tenant.leisure-schedule';

    public ?string $weekStart = null;

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant?->tenant_type;
        return $type === 'leisure';
    }

    public function mount(): void
    {
        $this->weekStart = $this->weekStart ?? now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->weekStart = CarbonImmutable::now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function getViewData(): array
    {
        $tenantId = auth()->user()?->tenant?->id;
        $start = CarbonImmutable::parse($this->weekStart);
        $end = $start->addDays(6);

        $days = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $days[] = $d;
        }

        $members = TenantTeamMember::query()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantTeamMember::STATUS_ACTIVE)
            ->with('user:id,name,email')
            ->get();

        $shifts = TenantTeamMemberShift::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(function ($s) {
                return $s->tenant_team_member_id . '|' . $s->shift_date->toDateString();
            });

        $totalsByMember = [];
        foreach ($members as $m) {
            $totalsByMember[$m->id] = 0;
        }
        foreach ($shifts as $cellShifts) {
            foreach ($cellShifts as $shift) {
                $totalsByMember[$shift->tenant_team_member_id] =
                    ($totalsByMember[$shift->tenant_team_member_id] ?? 0) + $shift->duration_minutes;
            }
        }

        return [
            'weekStart' => $start,
            'weekEnd' => $end,
            'days' => $days,
            'members' => $members,
            'shifts' => $shifts,
            'totalsByMember' => $totalsByMember,
            'leisureRoles' => TenantTeamMember::LEISURE_ROLES,
        ];
    }
}
