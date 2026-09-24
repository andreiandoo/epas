<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Support\Manual\PageManual;
use Illuminate\Http\JsonResponse;

/**
 * Rendered page manual for the in-page drawer ("Manual pagină"). Fetched on
 * the first open only, so the heavy edit pages don't carry the content.
 */
class ManualController extends Controller
{
    public function show(string $page): JsonResponse
    {
        abort_unless(PageManual::exists($page), 404);

        $manual = PageManual::load($page);

        return response()->json([
            'title' => $manual['title'],
            'tabs' => $manual['tabs'],
            'chapters' => array_map(fn (array $chapter) => [
                'id' => $chapter['id'],
                'tab' => $chapter['tab'],
            ], $manual['chapters']),
            'toc' => view('filament.marketplace.manual.toc', ['manual' => $manual])->render(),
            'content' => view('filament.marketplace.manual.content', ['manual' => $manual])->render(),
        ]);
    }
}
