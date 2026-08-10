<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortReport;
use App\Services\Shorts\ShortUgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendee-submitted shorts and viewer reports (B9, §14).
 */
class ShortUgcController extends BaseController
{
    public function __construct(private readonly ShortUgcService $ugc) {}

    /**
     * GET marketplace-client/customer/shorts/can-post?event_id=
     *
     * Lets the app hide the record button rather than letting someone film a
     * clip and only then be told they are not eligible.
     */
    public function eligibility(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate(['event_id' => ['required', 'integer']]);

        return $this->success([
            'event_id' => $validated['event_id'],
            'can_post' => $this->ugc->mayPost($customer, $validated['event_id']),
        ]);
    }

    /**
     * POST marketplace-client/customer/shorts — start a UGC upload.
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $event = Event::find($validated['event_id']);

        if (! $event) {
            return $this->error('Event not found', 404);
        }

        if (! $this->ugc->mayPost($customer, $event->id)) {
            return $this->error('You can only post from an event you attended.', 403);
        }

        try {
            $session = $this->ugc->createUpload($customer, $event, $validated['caption'] ?? null);
        } catch (\Throwable) {
            return $this->error('Uploads are not available right now.', 503);
        }

        return $this->success($session, 'Your short goes to review before it appears.', 201);
    }

    /**
     * POST tenant-client/shorts/{short}/report
     *
     * Reporting stays open to guests: someone who has not logged in still sees
     * the same feed, and requiring an account to report harmful content only
     * protects the content.
     */
    public function report(Request $request, int $short): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', ShortReport::REASONS)],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = Short::find($short);

        if (! $model) {
            return $this->error('Short not found', 404);
        }

        $customer = $request->user();

        $this->ugc->report(
            $model,
            $customer instanceof MarketplaceCustomer ? $customer : null,
            $validated['reason'],
            $validated['detail'] ?? null,
        );

        return $this->success(['short_id' => $model->id, 'reported' => true], 'Thanks — we will take a look.');
    }

    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (! $customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
