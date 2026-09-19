<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\MarketplaceNotification;
use App\Services\OrganizerNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Tells the operator the admin's decision on a location or product they sent
 * for approval (bell in the operator account).
 */
class ReviewNotifier
{
    public static function location(ActivityLocation $location, string $status): void
    {
        self::send(
            $location->organizer,
            $status,
            ActivityOrderBuilder::ro($location->name, 'Locația'),
            '/organizator/locatii/' . $location->id,
            $location->rejection_reason,
            $location
        );
    }

    public static function product(Activity $product, string $status): void
    {
        self::send(
            $product->organizer,
            $status,
            ActivityOrderBuilder::ro($product->title, 'Produsul'),
            '/organizator/produse/' . $product->id,
            $product->rejection_reason,
            $product
        );
    }

    private static function send($organizer, string $status, string $name, string $url, ?string $reason, $record): void
    {
        if (!$organizer || !in_array($status, ['approved', 'rejected'], true)) {
            return;
        }
        try {
            $approved = $status === 'approved';
            OrganizerNotificationService::notify(
                $organizer,
                $approved ? MarketplaceNotification::TYPE_EVENT_APPROVED : MarketplaceNotification::TYPE_EVENT_REJECTED,
                $approved ? "„{$name}” a fost aprobat și e publicat" : "„{$name}” nu a fost aprobat",
                $approved ? 'Îl poți ascunde oricând din contul tău.' : ($reason ?: 'Vezi observațiile și trimite din nou.'),
                $url,
                $record,
                ['kind' => 'activities_review', 'status' => $status]
            );
        } catch (\Throwable $e) {
            Log::warning('Activities review notification failed: ' . $e->getMessage());
        }
    }
}
