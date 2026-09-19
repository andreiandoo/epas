<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Services\Activities\CatalogPresenter;
use App\Services\Activities\OrganizerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Image uploads for the operator's locations and products. Files land in the
 * operator's own folder; the editors then send the returned path, and only
 * paths from that folder (or already on the record) are accepted.
 */
class UploadsController extends BaseController
{
    use ResolvesOrganizer;

    private const KINDS = ['location', 'location-gallery', 'room', 'lodging', 'product', 'product-gallery'];

    /** POST organizer/activities-module/uploads (multipart: file, kind) */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $v = Validator::make($request->all(), [
            'file' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:10240',
            'kind' => 'required|in:' . implode(',', self::KINDS),
        ], [
            'file.max'   => 'Poza poate avea cel mult 10 MB.',
            'file.mimes' => 'Poza trebuie să fie JPG, PNG sau WEBP.',
            'file.image' => 'Fișierul nu este o poză.',
        ]);
        if ($v->fails()) {
            return $this->error($v->errors()->first(), 422, ['errors' => $v->errors()->toArray()]);
        }

        $folder = (new OrganizerCatalog($organizer))->uploadFolder($request->input('kind'));
        $path = $request->file('file')->store($folder, 'public');

        return $this->success(['path' => $path, 'url' => CatalogPresenter::url($path)], null, 201);
    }
}
