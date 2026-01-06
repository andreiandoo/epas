<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewsController extends BaseController
{
    /**
     * Get customer's reviews
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $query = DB::table('marketplace_customer_reviews')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 10), 50);
        $reviews = $query->paginate($perPage);

        // Get event details for each review
        $eventIds = collect($reviews->items())->pluck('marketplace_event_id')->unique();
        $events = DB::table('marketplace_events')
            ->whereIn('id', $eventIds)
            ->get()
            ->keyBy('id');

        $formattedReviews = collect($reviews->items())->map(function ($review) use ($events) {
            $event = $events->get($review->marketplace_event_id);
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'text' => $review->text,
                'detailed_ratings' => json_decode($review->detailed_ratings, true),
                'photos' => json_decode($review->photos, true) ?? [],
                'recommend' => (bool) $review->recommend,
                'is_anonymous' => (bool) $review->is_anonymous,
                'status' => $review->status,
                'helpful_count' => $review->helpful_count ?? 0,
                'event' => $event ? [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at,
                    'image' => $event->image,
                ] : null,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedReviews,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Get events that can be reviewed (past events without review)
     */
    public function eventsToReview(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get past events the customer attended
        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '<', now());
            })
            ->with('marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image')
            ->get();

        // Get events already reviewed
        $reviewedEventIds = DB::table('marketplace_customer_reviews')
            ->where('marketplace_customer_id', $customer->id)
            ->pluck('marketplace_event_id')
            ->toArray();

        // Filter out already reviewed events
        $eventsToReview = $orders
            ->filter(function ($order) use ($reviewedEventIds) {
                return $order->marketplaceEvent && !in_array($order->marketplaceEvent->id, $reviewedEventIds);
            })
            ->map(function ($order) {
                $event = $order->marketplaceEvent;
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at->toIso8601String(),
                    'date_formatted' => $event->starts_at->format('d M Y'),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'image' => $event->image_url,
                    'order_id' => $order->id,
                ];
            })
            ->unique('id')
            ->values();

        return $this->success([
            'events' => $eventsToReview,
        ]);
    }

    /**
     * Create a new review
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'text' => 'required|string|min:20|max:2000',
            'detailed_ratings' => 'nullable|array',
            'detailed_ratings.show' => 'nullable|integer|min:1|max:5',
            'detailed_ratings.venue' => 'nullable|integer|min:1|max:5',
            'detailed_ratings.organization' => 'nullable|integer|min:1|max:5',
            'detailed_ratings.value' => 'nullable|integer|min:1|max:5',
            'recommend' => 'boolean',
            'anonymous' => 'boolean',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|max:5120', // 5MB max per photo
        ]);

        // Check if event exists and customer attended it
        $attended = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) use ($validated) {
                $q->where('id', $validated['event_id'])
                  ->where('starts_at', '<', now());
            })
            ->exists();

        if (!$attended) {
            return $this->error('You can only review events you have attended', 403);
        }

        // Check if already reviewed
        $existingReview = DB::table('marketplace_customer_reviews')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_event_id', $validated['event_id'])
            ->first();

        if ($existingReview) {
            return $this->error('You have already reviewed this event', 422);
        }

        // Handle photo uploads
        $photoUrls = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('reviews/' . $customer->id, 'public');
                $photoUrls[] = Storage::url($path);
            }
        }

        // Create review
        $reviewId = DB::table('marketplace_customer_reviews')->insertGetId([
            'marketplace_client_id' => $client->id,
            'marketplace_customer_id' => $customer->id,
            'marketplace_event_id' => $validated['event_id'],
            'rating' => $validated['rating'],
            'text' => $validated['text'],
            'detailed_ratings' => json_encode($validated['detailed_ratings'] ?? []),
            'photos' => json_encode($photoUrls),
            'recommend' => $validated['recommend'] ?? true,
            'is_anonymous' => $validated['anonymous'] ?? false,
            'status' => 'pending', // Requires moderation
            'helpful_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success([
            'review_id' => $reviewId,
        ], 'Review submitted successfully. It will be published after moderation.', 201);
    }

    /**
     * Get single review
     */
    public function show(Request $request, int $reviewId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $review = DB::table('marketplace_customer_reviews')
            ->where('id', $reviewId)
            ->where('marketplace_customer_id', $customer->id)
            ->first();

        if (!$review) {
            return $this->error('Review not found', 404);
        }

        // Get event details
        $event = DB::table('marketplace_events')
            ->where('id', $review->marketplace_event_id)
            ->first();

        return $this->success([
            'review' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'text' => $review->text,
                'detailed_ratings' => json_decode($review->detailed_ratings, true),
                'photos' => json_decode($review->photos, true) ?? [],
                'recommend' => (bool) $review->recommend,
                'is_anonymous' => (bool) $review->is_anonymous,
                'status' => $review->status,
                'helpful_count' => $review->helpful_count ?? 0,
                'event' => $event ? [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at,
                    'image' => $event->image,
                ] : null,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ],
        ]);
    }

    /**
     * Update a review
     */
    public function update(Request $request, int $reviewId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $review = DB::table('marketplace_customer_reviews')
            ->where('id', $reviewId)
            ->where('marketplace_customer_id', $customer->id)
            ->first();

        if (!$review) {
            return $this->error('Review not found', 404);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'text' => 'sometimes|string|min:20|max:2000',
            'detailed_ratings' => 'nullable|array',
            'recommend' => 'boolean',
            'anonymous' => 'boolean',
        ]);

        $updates = [
            'updated_at' => now(),
        ];

        if (isset($validated['rating'])) {
            $updates['rating'] = $validated['rating'];
        }
        if (isset($validated['text'])) {
            $updates['text'] = $validated['text'];
        }
        if (isset($validated['detailed_ratings'])) {
            $updates['detailed_ratings'] = json_encode($validated['detailed_ratings']);
        }
        if (isset($validated['recommend'])) {
            $updates['recommend'] = $validated['recommend'];
        }
        if (isset($validated['anonymous'])) {
            $updates['is_anonymous'] = $validated['anonymous'];
        }

        // Reset to pending for re-moderation if content changed
        if (isset($validated['rating']) || isset($validated['text'])) {
            $updates['status'] = 'pending';
        }

        DB::table('marketplace_customer_reviews')
            ->where('id', $reviewId)
            ->update($updates);

        return $this->success(null, 'Review updated successfully');
    }

    /**
     * Delete a review
     */
    public function destroy(Request $request, int $reviewId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $review = DB::table('marketplace_customer_reviews')
            ->where('id', $reviewId)
            ->where('marketplace_customer_id', $customer->id)
            ->first();

        if (!$review) {
            return $this->error('Review not found', 404);
        }

        // Delete associated photos
        $photos = json_decode($review->photos, true) ?? [];
        foreach ($photos as $photoUrl) {
            $path = str_replace('/storage/', '', $photoUrl);
            Storage::disk('public')->delete($path);
        }

        DB::table('marketplace_customer_reviews')
            ->where('id', $reviewId)
            ->delete();

        return $this->success(null, 'Review deleted successfully');
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
