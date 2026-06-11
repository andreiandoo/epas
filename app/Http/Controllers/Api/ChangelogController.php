<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ChangelogController extends Controller
{
    /**
     * Get changelog entries with filtering and grouping
     *
     * @queryParam module string Filter by module (marketplace, organizer, seating, etc.)
     * @queryParam type string Filter by type (feat, fix, refactor, etc.)
     * @queryParam from string Start date (Y-m-d)
     * @queryParam to string End date (Y-m-d)
     * @queryParam days int Last N days (default: 30)
     * @queryParam group_by string Group results by: date, module, type, week, month
     * @queryParam per_page int Items per page (default: 50)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChangelogEntry::visible()->orderBy('committed_at', 'desc');

        // Filter by module
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->where('committed_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('committed_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        // Filter by last N days (default behavior if no date filters)
        if (!$request->filled('from') && !$request->filled('to') && !$request->filled('all')) {
            $days = (int) $request->input('days', 30);
            if ($days > 0) {
                $query->where('committed_at', '>=', now()->subDays($days));
            }
        }

        // Search in message
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $entries = $query->paginate($perPage);

        // Transform entries
        $data = $entries->through(function ($entry) {
            return $this->transformEntry($entry);
        });

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
            'modules' => ChangelogEntry::MODULE_MAPPINGS,
            'types' => ChangelogEntry::TYPE_LABELS,
        ]);
    }

    /**
     * Get changelog grouped by a specific field
     */
    public function grouped(Request $request): JsonResponse
    {
        $groupBy = $request->input('group_by', 'module');
        $days = (int) $request->input('days', 30);

        $query = ChangelogEntry::visible()->orderBy('committed_at', 'desc');

        if ($days > 0) {
            $query->where('committed_at', '>=', now()->subDays($days));
        }

        $entries = $query->get();

        $grouped = match($groupBy) {
            'date' => $this->groupByDate($entries),
            'week' => $this->groupByWeek($entries),
            'month' => $this->groupByMonth($entries),
            'type' => $this->groupByType($entries),
            default => $this->groupByModule($entries),
        };

        return response()->json([
            'success' => true,
            'group_by' => $groupBy,
            'data' => $grouped,
        ]);
    }

    /**
     * Get changelog summary/stats
     */
    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        $query = ChangelogEntry::visible();

        if ($days > 0) {
            $query->where('committed_at', '>=', now()->subDays($days));
        }

        // Total counts
        $total = $query->count();

        // By module
        $byModule = ChangelogEntry::visible()
            ->when($days > 0, fn($q) => $q->where('committed_at', '>=', now()->subDays($days)))
            ->selectRaw('module, count(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'module' => $row->module,
                'label' => ChangelogEntry::MODULE_MAPPINGS[$row->module] ?? ucfirst($row->module),
                'count' => $row->count,
            ]);

        // By type
        $byType = ChangelogEntry::visible()
            ->when($days > 0, fn($q) => $q->where('committed_at', '>=', now()->subDays($days)))
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'label' => ChangelogEntry::TYPE_LABELS[$row->type] ?? ucfirst($row->type),
                'count' => $row->count,
            ]);

        // Total lines changed
        $linesChanged = ChangelogEntry::visible()
            ->when($days > 0, fn($q) => $q->where('committed_at', '>=', now()->subDays($days)))
            ->selectRaw('SUM(additions) as additions, SUM(deletions) as deletions')
            ->first();

        // Recent activity (commits per day for last 7 days)
        $recentActivity = ChangelogEntry::visible()
            ->where('committed_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(committed_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Latest commit
        $latest = ChangelogEntry::visible()->orderBy('committed_at', 'desc')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_commits' => $total,
                'period_days' => $days,
                'by_module' => $byModule,
                'by_type' => $byType,
                'lines_added' => (int) ($linesChanged->additions ?? 0),
                'lines_deleted' => (int) ($linesChanged->deletions ?? 0),
                'recent_activity' => $recentActivity,
                'latest_commit' => $latest ? $this->transformEntry($latest) : null,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get modules list with counts
     */
    public function modules(): JsonResponse
    {
        $modules = ChangelogEntry::visible()
            ->selectRaw('module, count(*) as count, MAX(committed_at) as last_update')
            ->groupBy('module')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'id' => $row->module,
                'label' => ChangelogEntry::MODULE_MAPPINGS[$row->module] ?? ucfirst($row->module),
                'count' => $row->count,
                'last_update' => Carbon::parse($row->last_update)->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Get single entry details
     */
    public function show(string $hash): JsonResponse
    {
        $entry = ChangelogEntry::where('commit_hash', $hash)
            ->orWhere('short_hash', $hash)
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Changelog entry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformEntry($entry, true),
        ]);
    }

    // Private helper methods

    private function transformEntry(ChangelogEntry $entry, bool $detailed = false): array
    {
        $data = [
            'id' => $entry->id,
            'hash' => $entry->short_hash,
            'type' => $entry->type,
            'type_label' => $entry->type_label,
            'module' => $entry->module,
            'module_label' => $entry->module_label,
            'message' => $entry->description,
            'is_breaking' => $entry->is_breaking,
            'committed_at' => $entry->committed_at->toIso8601String(),
            'committed_at_human' => $entry->committed_at->diffForHumans(),
        ];

        if ($detailed) {
            $data['full_hash'] = $entry->commit_hash;
            $data['full_message'] = $entry->message;
            $data['scope'] = $entry->scope;
            $data['author'] = [
                'name' => $entry->author_name,
                'email' => $entry->author_email,
            ];
            $data['files_changed'] = $entry->files_changed;
            $data['additions'] = $entry->additions;
            $data['deletions'] = $entry->deletions;
        }

        return $data;
    }

    private function groupByModule($entries): array
    {
        return $entries->groupBy('module')
            ->map(function ($items, $module) {
                return [
                    'module' => $module,
                    'label' => ChangelogEntry::MODULE_MAPPINGS[$module] ?? ucfirst($module),
                    'count' => $items->count(),
                    'entries' => $items->map(fn($e) => $this->transformEntry($e))->values(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->toArray();
    }

    private function groupByType($entries): array
    {
        return $entries->groupBy('type')
            ->map(function ($items, $type) {
                return [
                    'type' => $type,
                    'label' => ChangelogEntry::TYPE_LABELS[$type] ?? ucfirst($type),
                    'count' => $items->count(),
                    'entries' => $items->map(fn($e) => $this->transformEntry($e))->values(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->toArray();
    }

    private function groupByDate($entries): array
    {
        return $entries->groupBy(fn($e) => $e->committed_at->format('Y-m-d'))
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'date_formatted' => Carbon::parse($date)->format('d F Y'),
                    'count' => $items->count(),
                    'entries' => $items->map(fn($e) => $this->transformEntry($e))->values(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function groupByWeek($entries): array
    {
        return $entries->groupBy(fn($e) => $e->committed_at->startOfWeek()->format('Y-m-d'))
            ->map(function ($items, $weekStart) {
                $start = Carbon::parse($weekStart);
                return [
                    'week_start' => $weekStart,
                    'week_end' => $start->copy()->endOfWeek()->format('Y-m-d'),
                    'label' => 'Săpt. ' . $start->format('d M') . ' - ' . $start->copy()->endOfWeek()->format('d M Y'),
                    'count' => $items->count(),
                    'entries' => $items->map(fn($e) => $this->transformEntry($e))->values(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function groupByMonth($entries): array
    {
        return $entries->groupBy(fn($e) => $e->committed_at->format('Y-m'))
            ->map(function ($items, $month) {
                return [
                    'month' => $month,
                    'label' => Carbon::parse($month . '-01')->translatedFormat('F Y'),
                    'count' => $items->count(),
                    'entries' => $items->map(fn($e) => $this->transformEntry($e))->values(),
                ];
            })
            ->values()
            ->toArray();
    }
}
