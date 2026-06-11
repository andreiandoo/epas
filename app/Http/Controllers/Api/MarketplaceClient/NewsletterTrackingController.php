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

            // Update pivot to unsubscribed status
            $customer->contactLists()
                ->whereIn('marketplace_contact_lists.id', $listIds)
                ->update([
                    'marketplace_contact_list_members.status' => 'unsubscribed',
                    'marketplace_contact_list_members.unsubscribed_at' => now(),
                ]);
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
            $log = MarketplaceEmailLog::where('to_email', $recipient->email)
                ->where('template_slug', 'newsletter')
                ->whereJsonContains('metadata->recipient_id', $recipient->id)
                ->first();

            if (!$log) return;

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
