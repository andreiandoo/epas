<?php

namespace App\Services\OperatorCockpit;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ce primește un operator în bară și în jurnal, după departamentele de suport din care face parte.
 *
 * - super-adminul vede tot;
 * - un operator fără departament vede tot (cât îi permite rolul);
 * - altfel: reuniunea a ce primesc departamentele lui; un departament nesetat înseamnă „tot”.
 */
class DepartmentScope
{
    public const CATEGORIES = [
        'chat' => 'Chat cu clienții',
        'ticket' => 'Tichete suport',
        'todo' => 'ToDos',
        'artist' => 'Conturi artist în review',
        'event' => 'Evenimente de revizuit',
        'service' => 'Servicii (comenzi de activat / plăți de confirmat)',
        'docs' => 'Documente evenimente (vizare, impozit, PV distrugere)',
    ];

    /** @var array<int, array{categories: ?array, journal_types: ?array}> */
    private static array $cache = [];

    /**
     * @return array{categories: ?array, journal_types: ?array} null = fără restricție
     */
    public static function for(?MarketplaceAdmin $admin): array
    {
        $none = ['categories' => null, 'journal_types' => null];
        if (! $admin || $admin->isSuperAdmin() || session('marketplace_is_super_admin')) {
            return $none;
        }

        if (isset(self::$cache[$admin->id])) {
            return self::$cache[$admin->id];
        }

        if (! Schema::hasTable('support_department_admins') || ! Schema::hasColumn('support_departments', 'cockpit_scope')) {
            return self::$cache[$admin->id] = $none;
        }

        $scopes = DB::table('support_departments')
            ->join('support_department_admins', 'support_department_admins.support_department_id', '=', 'support_departments.id')
            ->where('support_department_admins.marketplace_admin_id', $admin->id)
            ->where('support_departments.is_active', true)
            ->pluck('support_departments.cockpit_scope')
            ->map(fn ($json) => is_string($json) ? (json_decode($json, true) ?: []) : (array) $json)
            ->all();

        if (! $scopes) {
            return self::$cache[$admin->id] = $none;
        }

        $categories = [];
        $journal = [];
        $allCategories = false;
        $allJournal = false;
        foreach ($scopes as $scope) {
            $c = array_values(array_filter((array) ($scope['categories'] ?? [])));
            $j = array_values(array_filter((array) ($scope['journal_types'] ?? [])));
            $c ? array_push($categories, ...$c) : $allCategories = true;
            $j ? array_push($journal, ...$j) : $allJournal = true;
        }

        return self::$cache[$admin->id] = [
            'categories' => $allCategories ? null : array_values(array_unique($categories)),
            'journal_types' => $allJournal ? null : array_values(array_unique($journal)),
        ];
    }

    public static function journalTypeOptions(): array
    {
        return MarketplaceNotification::getTypeLabels();
    }
}
