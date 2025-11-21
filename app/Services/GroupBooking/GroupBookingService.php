<?php

namespace App\Services\GroupBooking;

use App\Models\GroupBooking;
use App\Models\GroupBookingMember;
use App\Models\GroupPricingTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupBookingService
{
    /**
     * Create a new group booking
     */
    public function create(array $data): array
    {
        DB::beginTransaction();
        try {
            // Calculate discount based on group size
            $discount = $this->calculateDiscount(
                $data['tenant_id'],
                $data['event_id'] ?? null,
                $data['total_tickets']
            );

            $totalAmount = $data['ticket_price'] * $data['total_tickets'];
            $discountAmount = $totalAmount * ($discount / 100);

            $booking = GroupBooking::create([
                'tenant_id' => $data['tenant_id'],
                'event_id' => $data['event_id'],
                'organizer_customer_id' => $data['organizer_customer_id'],
                'group_name' => $data['group_name'],
                'group_type' => $data['group_type'] ?? 'corporate',
                'total_tickets' => $data['total_tickets'],
                'total_amount' => $totalAmount,
                'discount_percentage' => $discount,
                'discount_amount' => $discountAmount,
                'payment_type' => $data['payment_type'] ?? 'full',
                'notes' => $data['notes'] ?? null,
                'deadline_at' => $data['deadline_at'] ?? now()->addDays(7),
            ]);

            DB::commit();
            return ['success' => true, 'booking' => $booking];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add members to group booking
     */
    public function addMembers(GroupBooking $booking, array $members): array
    {
        $amountPerPerson = $booking->getFinalAmount() / count($members);
        $created = [];

        foreach ($members as $member) {
            $created[] = GroupBookingMember::create([
                'group_booking_id' => $booking->id,
                'name' => $member['name'],
                'email' => $member['email'],
                'phone' => $member['phone'] ?? null,
                'amount_due' => $booking->payment_type === 'split' ? $amountPerPerson : 0,
                'payment_link' => $booking->payment_type === 'split'
                    ? url('/pay/group/' . Str::random(32)) : null,
            ]);
        }

        $booking->update(['total_tickets' => count($members)]);

        return ['success' => true, 'members' => $created];
    }

    /**
     * Import members from CSV
     */
    public function importMembers(GroupBooking $booking, array $csvData): array
    {
        $members = [];
        foreach ($csvData as $row) {
            $members[] = [
                'name' => $row['name'] ?? $row[0] ?? '',
                'email' => $row['email'] ?? $row[1] ?? '',
                'phone' => $row['phone'] ?? $row[2] ?? null,
            ];
        }

        return $this->addMembers($booking, $members);
    }

    /**
     * Process member payment
     */
    public function processMemberPayment(GroupBookingMember $member, float $amount): array
    {
        $member->update([
            'amount_paid' => $member->amount_paid + $amount,
            'payment_status' => ($member->amount_paid + $amount) >= $member->amount_due ? 'paid' : 'partial',
            'paid_at' => ($member->amount_paid + $amount) >= $member->amount_due ? now() : null,
        ]);

        // Check if all members paid
        $booking = $member->booking;
        $allPaid = $booking->members()->where('payment_status', '!=', 'paid')->count() === 0;

        if ($allPaid) {
            $booking->update(['status' => 'paid']);
        }

        return ['success' => true, 'member' => $member->fresh()];
    }

    /**
     * Confirm booking and issue tickets
     */
    public function confirm(GroupBooking $booking): array
    {
        if ($booking->members()->count() !== $booking->total_tickets) {
            return ['success' => false, 'error' => 'Member count does not match ticket count'];
        }

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Send confirmation emails to all members
        foreach ($booking->members as $member) {
            // Mail::to($member->email)->queue(new GroupBookingConfirmation($booking, $member));
        }

        return ['success' => true, 'booking' => $booking->fresh()];
    }

    /**
     * Calculate group discount
     */
    public function calculateDiscount(string $tenantId, ?int $eventId, int $ticketCount): float
    {
        $tier = GroupPricingTier::where('tenant_id', $tenantId)
            ->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)->orWhereNull('event_id');
            })
            ->where('enabled', true)
            ->where('min_tickets', '<=', $ticketCount)
            ->where(function ($q) use ($ticketCount) {
                $q->whereNull('max_tickets')->orWhere('max_tickets', '>=', $ticketCount);
            })
            ->orderBy('discount_percentage', 'desc')
            ->first();

        return $tier ? $tier->discount_percentage : 0;
    }

    /**
     * Get booking statistics
     */
    public function getStats(string $tenantId): array
    {
        return [
            'total_bookings' => GroupBooking::forTenant($tenantId)->count(),
            'confirmed' => GroupBooking::forTenant($tenantId)->confirmed()->count(),
            'total_tickets' => GroupBooking::forTenant($tenantId)->confirmed()->sum('total_tickets'),
            'total_revenue' => GroupBooking::forTenant($tenantId)
                ->where('status', 'paid')
                ->selectRaw('SUM(total_amount - discount_amount) as total')
                ->value('total') ?? 0,
        ];
    }
}
