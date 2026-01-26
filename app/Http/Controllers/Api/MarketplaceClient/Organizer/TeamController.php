<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class TeamController extends BaseController
{
    /**
     * Get team members list
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $members = $organizer->teamMembers()
            ->orderByRaw("FIELD(role, 'admin', 'manager', 'staff')")
            ->orderByRaw("FIELD(status, 'active', 'pending', 'inactive')")
            ->orderBy('name')
            ->get();

        // Add the owner as first "member"
        $teamData = [];

        // Owner entry
        $teamData[] = [
            'id' => 'owner_' . $organizer->id,
            'name' => $organizer->contact_name ?? $organizer->name,
            'email' => $organizer->email,
            'role' => 'owner',
            'permissions' => ['events', 'orders', 'reports', 'team', 'checkin'],
            'status' => 'active',
            'is_current_user' => true,
            'created_at' => $organizer->created_at->toIso8601String(),
        ];

        // Team members
        foreach ($members as $member) {
            $teamData[] = [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'permissions' => $member->getEffectivePermissions(),
                'status' => $member->status,
                'is_current_user' => false,
                'invite_sent_at' => $member->invite_sent_at?->toIso8601String(),
                'accepted_at' => $member->accepted_at?->toIso8601String(),
                'created_at' => $member->created_at->toIso8601String(),
            ];
        }

        return $this->success([
            'members' => $teamData,
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
            ],
            'stats' => [
                'total' => count($teamData),
                'active' => collect($teamData)->where('status', 'active')->count(),
                'pending' => collect($teamData)->where('status', 'pending')->count(),
                'admins' => collect($teamData)->whereIn('role', ['owner', 'admin'])->count(),
            ],
        ]);
    }

    /**
     * Invite a new team member
     */
    public function invite(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,manager,staff',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:events,orders,reports,team,checkin',
        ]);

        // Check if email already exists for this organizer
        if ($organizer->email === $validated['email']) {
            return $this->error('Nu poti invita adresa ta de email', 422);
        }

        if ($organizer->teamMembers()->where('email', $validated['email'])->exists()) {
            return $this->error('Acest email este deja in echipa', 422);
        }

        // Limit team size
        $maxTeamSize = $organizer->marketplaceClient->settings['max_team_size'] ?? 10;
        if ($organizer->teamMembers()->count() >= $maxTeamSize) {
            return $this->error('Ai atins numarul maxim de membri in echipa (' . $maxTeamSize . ')', 422);
        }

        // Set permissions based on role
        $permissions = $validated['permissions'] ?? [];
        if ($validated['role'] === 'admin') {
            $permissions = ['events', 'orders', 'reports', 'team', 'checkin'];
        } elseif ($validated['role'] === 'staff' && empty($permissions)) {
            $permissions = ['checkin'];
        }

        $member = MarketplaceOrganizerTeamMember::create([
            'marketplace_organizer_id' => $organizer->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'permissions' => $permissions,
            'status' => 'pending',
        ]);

        // Generate invite token and send email
        $token = $member->generateInviteToken();
        $this->sendInviteEmail($member, $organizer, $token);

        return $this->success([
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'permissions' => $member->getEffectivePermissions(),
                'status' => $member->status,
            ],
        ], 'Invitatie trimisa cu succes');
    }

    /**
     * Update a team member
     */
    public function update(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'member_id' => 'required|string',
            'role' => 'required|in:admin,manager,staff',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:events,orders,reports,team,checkin',
        ]);

        // Can't update owner
        if (str_starts_with($validated['member_id'], 'owner_')) {
            return $this->error('Nu poti modifica proprietarul', 422);
        }

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        // Set permissions based on role
        $permissions = $validated['permissions'] ?? [];
        if ($validated['role'] === 'admin') {
            $permissions = ['events', 'orders', 'reports', 'team', 'checkin'];
        }

        $member->update([
            'role' => $validated['role'],
            'permissions' => $permissions,
        ]);

        return $this->success([
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'permissions' => $member->getEffectivePermissions(),
                'status' => $member->status,
            ],
        ], 'Membru actualizat cu succes');
    }

    /**
     * Remove a team member
     */
    public function remove(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'member_id' => 'required|string',
        ]);

        // Can't remove owner
        if (str_starts_with($validated['member_id'], 'owner_')) {
            return $this->error('Nu poti elimina proprietarul', 422);
        }

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        $member->delete();

        return $this->success(null, 'Membru eliminat cu succes');
    }

    /**
     * Resend invite to a pending member
     */
    public function resendInvite(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'member_id' => 'required|string',
        ]);

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        if (!$member->isPending()) {
            return $this->error('Invitatia poate fi retrimisa doar pentru membrii in asteptare', 422);
        }

        if (!$member->canResendInvite()) {
            return $this->error('Te rugam sa astepti cateva minute inainte de a retrimite invitatia', 429);
        }

        $token = $member->generateInviteToken();
        $this->sendInviteEmail($member, $organizer, $token);

        return $this->success(null, 'Invitatie retrimisa cu succes');
    }

    /**
     * Resend all pending invites
     */
    public function resendAllInvites(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $pendingMembers = $organizer->teamMembers()
            ->where('status', 'pending')
            ->get();

        $sent = 0;
        foreach ($pendingMembers as $member) {
            if ($member->canResendInvite()) {
                $token = $member->generateInviteToken();
                $this->sendInviteEmail($member, $organizer, $token);
                $sent++;
            }
        }

        if ($sent === 0) {
            return $this->error('Nu exista invitatii care pot fi retrimise in acest moment', 422);
        }

        return $this->success([
            'sent_count' => $sent,
        ], $sent . ' invitatii au fost retrimise');
    }

    /**
     * Send invite email to team member
     */
    protected function sendInviteEmail(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, string $token): void
    {
        $client = $organizer->marketplaceClient;
        $inviteUrl = 'https://' . $client->domain . '/organizator/accept-invite?token=' . $token . '&email=' . urlencode($member->email);

        // Simple email for now - can be replaced with a proper notification later
        try {
            Mail::send([], [], function ($message) use ($member, $organizer, $inviteUrl) {
                $message->to($member->email, $member->name)
                    ->subject('Invitatie in echipa ' . $organizer->name)
                    ->html($this->getInviteEmailHtml($member, $organizer, $inviteUrl));
            });
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send team invite email', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get HTML for invite email
     */
    protected function getInviteEmailHtml(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, string $inviteUrl): string
    {
        $roleLabel = match ($member->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            default => ucfirst($member->role),
        };

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background: #E91E63; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Bună {$member->name},</h2>

        <p>Ai fost invitat să te alături echipei <strong>{$organizer->name}</strong> ca <strong>{$roleLabel}</strong>.</p>

        <p>Apasă pe butonul de mai jos pentru a accepta invitația și a-ți crea contul:</p>

        <a href="{$inviteUrl}" class="button">Acceptă invitația</a>

        <p>Sau copiază acest link în browser:</p>
        <p style="word-break: break-all; color: #666; font-size: 14px;">{$inviteUrl}</p>

        <p><strong>Notă:</strong> Această invitație expiră în 7 zile.</p>

        <div class="footer">
            <p>Dacă nu ai solicitat această invitație, poți ignora acest email.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
