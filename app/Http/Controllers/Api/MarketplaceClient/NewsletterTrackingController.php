<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceNewsletterRecipient;
use App\Models\MarketplaceEmailLog;
use App\Models\MarketplaceContactList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NewsletterTrackingController extends Controller
{
    /**
     * Track email open (1x1 pixel)
     */
    public function trackOpen(Request $request)
    {
        $id = $request->input('id');
        $token = $request->input('token');

        if (!$id || !$token) {
            return $this->transparentPixel();
        }

        $recipient = MarketplaceNewsletterRecipient::find($id);

        if (!$recipient) {
            return $this->transparentPixel();
        }

        // Verify token
        $expectedToken = hash('sha256', $recipient->id . 'open' . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            return $this->transparentPixel();
        }

        // Guard against false opens: if the send bounced, an "open" is almost
        // always the recipient's mail server / a security scanner fetching the
        // tracking pixel of an undelivered message — not a human. Recording it
        // would flip a bounced recipient to "opened".
        if ($recipient->status === 'bounced' || $recipient->bounced_at !== null) {
            return $this->transparentPixel();
        }

        // Mark as opened
        $recipient->markOpened();

        // Also update the corresponding email log
        $this->updateEmailLog($recipient, 'opened');

        return $this->transparentPixel();
    }

    /**
     * Track link click
     */
    public function trackClick(Request $request)
    {
        $id = $request->input('id');
        $token = $request->input('token');
        $url = $request->input('url');

        if (!$id || !$token || !$url) {
            return redirect('/');
        }

        $recipient = MarketplaceNewsletterRecipient::find($id);

        if (!$recipient) {
            return redirect($url);
        }

        // Verify token
        $expectedToken = hash('sha256', $recipient->id . 'click' . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            return redirect($url);
        }

        // Guard against false clicks on a bounced send (link scanners) — same
        // reasoning as trackOpen. Still redirect so a real user isn't blocked.
        if ($recipient->status === 'bounced' || $recipient->bounced_at !== null) {
            return redirect($url);
        }

        // Mark as clicked
        $recipient->markClicked();

        // Also update the corresponding email log
        $this->updateEmailLog($recipient, 'clicked');

        return redirect($url);
    }

    /**
     * Unsubscribe from newsletter
     */
    public function unsubscribe(Request $request)
    {
        $id = $request->input('id');
        $token = $request->input('token');

        if (!$id || !$token) {
            return response()->view('marketplace.unsubscribe-error', [], 400);
        }

        $recipient = MarketplaceNewsletterRecipient::with(['newsletter.marketplaceClient', 'customer'])
            ->find($id);

        if (!$recipient) {
            return response()->view('marketplace.unsubscribe-error', [], 404);
        }

        // Verify token
        $expectedToken = hash('sha256', $recipient->id . $recipient->email . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            return response()->view('marketplace.unsubscribe-error', [], 403);
        }

        // Mark recipient as unsubscribed
        $recipient->markUnsubscribed();

        // If customer exists, remove from all contact lists
        if ($recipient->customer) {
            $customer = $recipient->customer;
            $marketplace = $recipient->newsletter->marketplaceClient;

            // Get all contact lists for this marketplace
            $listIds = MarketplaceContactList::where('marketplace_client_id', $marketplace->id)
                ->pluck('id');

            // Mark the customer's memberships in every marketplace list as
            // unsubscribed. NB: calling ->update() on the belongsToMany relation
            // targets the RELATED table (marketplace_contact_lists), which has no
            // `status` column — that was the 500 ("column status does not exist").
            // Pivot rows must be updated on the pivot table directly.
            \Illuminate\Support\Facades\DB::table('marketplace_contact_list_members')
                ->where('marketplace_customer_id', $customer->id)
                ->whereIn('list_id', $listIds)
                ->update([
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Immediately enroll the customer into any dynamic list that targets
            // active unsubscribers (e.g. "Dezabonați activ"). The scheduled
            // contact-lists:sync only runs once a day at 03:00, but the
            // requirement is that every unsubscribe surfaces in that list right
            // away — so we sync the matching lists for this single customer now.
            $unsubLists = MarketplaceContactList::where('marketplace_client_id', $marketplace->id)
                ->where('list_type', 'dynamic')
                ->get()
                ->filter(fn ($list) => collect($list->getRules())
                    ->pluck('type')
                    ->contains('has_actively_unsubscribed'));

            foreach ($unsubLists as $list) {
                // Rules are ANDed; confirm the customer matches the list's full
                // rule set before enrolling (markUnsubscribed above already set
                // recipient.status='unsubscribed', so the rule now matches).
                if ($list->buildMatchingCustomersQuery()->whereKey($customer->id)->exists()) {
                    $list->addSubscriber($customer);
                }
            }
        }

        return response()->view('marketplace.unsubscribe-success', [
            'marketplace' => $recipient->newsletter->marketplaceClient->name ?? 'Marketplace',
        ]);
    }

    /**
     * Manage subscription preferences
     */
    public function preferences(Request $request)
    {
        $customerId = $request->input('customer_id');
        $token = $request->input('token');

        if (!$customerId || !$token) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        // Verify token
        $expectedToken = hash('sha256', $customerId . 'preferences' . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Invalid token'], 403);
        }

        $customer = \App\Models\MarketplaceCustomer::with('contactLists')->find($customerId);

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        // Get all available lists for this marketplace
        $allLists = MarketplaceContactList::where('marketplace_client_id', $customer->marketplace_client_id)
            ->where('is_active', true)
            ->get()
            ->map(function ($list) use ($customer) {
                $subscription = $customer->contactLists->firstWhere('id', $list->id);
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'subscribed' => $subscription && $subscription->pivot->status === 'subscribed',
                ];
            });

        return response()->json([
            'customer' => [
                'email' => $customer->email,
                'accepts_marketing' => $customer->accepts_marketing,
            ],
            'lists' => $allLists,
        ]);
    }

    /**
     * Update subscription preferences
     */
    public function updatePreferences(Request $request)
    {
        $customerId = $request->input('customer_id');
        $token = $request->input('token');

        if (!$customerId || !$token) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        // Verify token
        $expectedToken = hash('sha256', $customerId . 'preferences' . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Invalid token'], 403);
        }

        $customer = \App\Models\MarketplaceCustomer::find($customerId);

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        // Update marketing consent
        if ($request->has('accepts_marketing')) {
            $customer->update([
                'accepts_marketing' => $request->boolean('accepts_marketing'),
                'marketing_consent_at' => $request->boolean('accepts_marketing') ? now() : null,
            ]);
        }

        // Update list subscriptions
        if ($request->has('lists')) {
            foreach ($request->input('lists') as $listId => $subscribed) {
                $list = MarketplaceContactList::where('id', $listId)
                    ->where('marketplace_client_id', $customer->marketplace_client_id)
                    ->first();

                if (!$list) {
                    continue;
                }

                if ($subscribed) {
                    $list->addSubscriber($customer->id);
                } else {
                    $list->removeSubscriber($customer->id);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Preferences updated']);
    }

    /**
     * Update the corresponding MarketplaceEmailLog entry for this recipient
     */
    protected function updateEmailLog(MarketplaceNewsletterRecipient $recipient, string $action): void
    {
        try {
            // metadata->recipient_id is a scalar JSON value, so match it with a
            // JSON-path equality (where), NOT whereJsonContains — the latter uses
            // array-containment semantics and never matches a scalar on Postgres,
            // which is why newsletter logs used to stay at status=sent.
            $log = MarketplaceEmailLog::where('to_email', $recipient->email)
                ->where('template_slug', 'newsletter')
                ->where('metadata->recipient_id', $recipient->id)
                ->first();

            if (!$log) return;

            // Never resurrect a bounced log into opened/clicked.
            if ($log->bounced_at !== null) return;

            if ($action === 'opened') {
                $log->markOpened();
            } elseif ($action === 'clicked') {
                $log->markClicked();
            }
        } catch (\Exception $e) {
            // Don't let log updates break tracking
        }
    }

    /**
     * Return a 1x1 transparent GIF pixel
     */
    protected function transparentPixel(): Response
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
