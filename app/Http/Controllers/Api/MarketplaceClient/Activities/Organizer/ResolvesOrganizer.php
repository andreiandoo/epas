<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Models\MarketplaceOrganizer;
use App\Services\Activities\OrganizerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesOrganizer
{
    /** The signed-in operator, and only on its own marketplace. */
    protected function organizer(Request $request): MarketplaceOrganizer
    {
        $client = $this->requireClient($request);
        $organizer = $request->user();
        if (!$organizer instanceof MarketplaceOrganizer || (int) $organizer->marketplace_client_id !== (int) $client->id) {
            abort(401, 'Unauthorized');
        }
        return $organizer;
    }

    protected function invalid(ValidationException $e): JsonResponse
    {
        return $this->error(OrganizerCatalog::firstError($e), 422, ['errors' => $e->errors()]);
    }
}
