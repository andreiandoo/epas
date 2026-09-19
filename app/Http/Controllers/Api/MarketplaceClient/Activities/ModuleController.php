<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entry point of the activities module (locations, access tickets,
 * experiences, packages). Every route in routes/activities.php sits behind
 * `marketplace.microservice:activities-module`, so a marketplace without the
 * module never reaches this namespace.
 */
class ModuleController extends BaseController
{
    /**
     * GET /marketplace-client/activities-module/status
     *
     * Tells the site which parts of the module it can show.
     */
    public function status(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        return $this->success([
            'enabled' => true,
            'modules' => [
                'activities' => true,
                'discovery'  => $client->hasMicroservice('discovery-module'),
            ],
        ]);
    }
}
