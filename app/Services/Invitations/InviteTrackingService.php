<?php

namespace App\Services\Invitations;

use App\Models\Invite;
use App\Models\InviteLog;

/**
 * Invite Tracking Service
 *
 * Handles status tracking including pixel tracking for email opens
 */
class InviteTrackingService
{
    /**
     * Track email open via pixel
     *
     * @param string $inviteCode
     * @param string|null $ip
     * @param string|null $userAgent
     * @return bool
     */
    public function trackOpen(string $inviteCode, ?string $ip = null, ?string $userAgent = null): bool
    {
        $invite = Invite::where('invite_code', $inviteCode)->first();

        if (!$invite) {
            return false;
        }

        if ($invite->wasOpened()) {
            return true; // Already tracked
        }

        $invite->markAsOpened();

        InviteLog::logOpen($invite, $ip, $userAgent);

        return true;
    }

    /**
     * Track check-in at gate
     *
     * @param string $qrData
     * @param string $gateRef
     * @return array {success, message, invite_code}
     */
    public function trackCheckIn(string $qrData, string $gateRef): array
    {
        // Parse QR data to get invite code
        if (!str_starts_with($qrData, 'INV:')) {
            return [
                'success' => false,
                'message' => 'Invalid QR code format',
            ];
        }

        $parts = explode(':', $qrData);
        $inviteCode = $parts[1] ?? null;

        if (!$inviteCode) {
            return [
                'success' => false,
                'message' => 'Invalid invitation code',
            ];
        }

        $invite = Invite::where('invite_code', $inviteCode)->first();

        if (!$invite) {
            return [
                'success' => false,
                'message' => 'Invitation not found',
            ];
        }

        if ($invite->isVoid()) {
            return [
                'success' => false,
                'message' => 'This invitation has been voided',
                'invite_code' => $inviteCode,
            ];
        }

        if ($invite->wasCheckedIn()) {
            return [
                'success' => false,
                'message' => 'Invitation already scanned',
                'invite_code' => $inviteCode,
                'scanned_at' => $invite->checked_in_at->toIso8601String(),
                'gate' => $invite->gate_ref,
            ];
        }

        // Mark as checked in
        $invite->markAsCheckedIn($gateRef);

        InviteLog::logCheckIn($invite, $gateRef);

        return [
            'success' => true,
            'message' => 'Invitation validated successfully',
            'invite_code' => $inviteCode,
            'recipient' => $invite->getRecipientName(),
            'seat' => $invite->seat_ref,
        ];
    }

    /**
     * Void an invitation
     *
     * @param Invite $invite
     * @param string|null $reason
     * @return bool
     */
    public function voidInvite(Invite $invite, ?string $reason = null): bool
    {
        if (!$invite->canBeVoided()) {
            return false;
        }

        $invite->markAsVoid();

        InviteLog::logVoid($invite, null, $reason);

        return true;
    }

    /**
     * Get tracking summary for an invite
     *
     * @param Invite $invite
     * @return array
     */
    public function getTrackingSummary(Invite $invite): array
    {
        return [
            'invite_code' => $invite->invite_code,
            'status' => $invite->status,
            'rendered' => $invite->rendered_at !== null,
            'rendered_at' => $invite->rendered_at?->toIso8601String(),
            'emailed' => $invite->wasEmailed(),
            'emailed_at' => $invite->emailed_at?->toIso8601String(),
            'delivered' => $invite->delivery_status === 'delivered',
            'delivery_status' => $invite->delivery_status,
            'downloaded' => $invite->wasDownloaded(),
            'downloaded_at' => $invite->downloaded_at?->toIso8601String(),
            'opened' => $invite->wasOpened(),
            'opened_at' => $invite->opened_at?->toIso8601String(),
            'checked_in' => $invite->wasCheckedIn(),
            'checked_in_at' => $invite->checked_in_at?->toIso8601String(),
            'gate' => $invite->gate_ref,
            'void' => $invite->isVoid(),
            'voided_at' => $invite->voided_at?->toIso8601String(),
        ];
    }
}
