<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

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
            ->with('events:id')
            ->orderByRaw(DB::getDriverName() === 'pgsql'
                ? "ARRAY_POSITION(ARRAY['admin', 'manager', 'staff'], role)"
                : "FIELD(role, 'admin', 'manager', 'staff')"
            )
            ->orderByRaw(DB::getDriverName() === 'pgsql'
                ? "ARRAY_POSITION(ARRAY['active', 'pending', 'inactive'], status)"
                : "FIELD(status, 'active', 'pending', 'inactive')"
            )
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
                'leisure_role' => $member->leisure_role,
                'permissions' => $member->getEffectivePermissions(),
                'gate_id' => $member->gate_id,
                'status' => $member->status,
                // event_ids = [] means "all events" (legacy); a non-empty list
                // is an explicit whitelist for non-admin members. Admins always
                // have access to everything regardless of the pivot.
                'event_ids' => $member->role === 'admin' ? [] : $member->events->pluck('id')->all(),
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
     * Add a new team member directly. The endpoint kept the legacy /team/invite
     * URL for compatibility, but the flow is now: organizer fills name + email
     * + password + role + (optional) gate, the member is created as active,
     * and we send a welcome email with their credentials + the app download
     * link instead of a tokenised invite.
     */
    public function invite(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:100',
            'role' => 'required|in:admin,manager,staff',
            'leisure_role' => 'nullable|in:check_in,rental_boats,rental_pontoon,validation_pontoon,rental_sled,validation_tow,pos_cashier,admin_mobile,kiosk_selfcheckin',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:events,orders,reports,team,checkin',
            'gate_id' => 'nullable|integer',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:events,id',
            // Cand false, nu trimitem emailul de welcome (admin comunica parola direct).
            // Util pentru conturi tehnice (kiosk tableta, cont robot etc.).
            'send_welcome_email' => 'nullable|boolean',
        ]);

        // Name is optional now — fall back to the email's local part so the
        // member always has *something* to display in lists and emails.
        $resolvedName = trim((string) ($validated['name'] ?? ''));
        if ($resolvedName === '') {
            $emailLocal = strstr($validated['email'], '@', true) ?: $validated['email'];
            $resolvedName = $emailLocal;
        }

        // Block adding the organizer's own email (would clash with the owner login).
        if ($organizer->email === $validated['email']) {
            return $this->error('Nu poti adauga adresa ta de email', 422);
        }

        // Block duplicate email on the SAME organizer (DB has composite unique
        // on marketplace_organizer_id + email). Adding the same email to a
        // DIFFERENT organizer in the same marketplace is intentionally allowed —
        // a staff member can scan/operate for multiple organizers with one login.
        if ($organizer->teamMembers()->where('email', $validated['email'])->exists()) {
            return $this->error('Acest email este deja in echipa acestui organizator', 422);
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

        $hashedPassword = bcrypt($validated['password']);

        $member = MarketplaceOrganizerTeamMember::create([
            'marketplace_organizer_id' => $organizer->id,
            'name' => $resolvedName,
            'email' => $validated['email'],
            'password' => $hashedPassword,
            'role' => $validated['role'],
            'leisure_role' => $validated['leisure_role'] ?? null,
            'permissions' => $permissions,
            'gate_id' => $validated['gate_id'] ?? null,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        // Per-event whitelist. Admins always have access to all events
        // (no filtering applied at query time), so we skip writing the pivot
        // for them. For manager/staff, an empty array means "all events"
        // (legacy behavior); a non-empty array restricts access.
        if ($validated['role'] !== 'admin' && !empty($validated['event_ids'])) {
            $allowedEventIds = Event::whereIn('id', $validated['event_ids'])
                ->where('marketplace_organizer_id', $organizer->id)
                ->pluck('id')
                ->all();
            $member->events()->sync($allowedEventIds);
        }

        // Cross-organizer password sync: if the same email exists on other
        // active members within this marketplace, propagate the new password
        // to all of them so one login works everywhere. The booted() observer
        // only fires on update, not create — so we do it explicitly here.
        MarketplaceOrganizerTeamMember::query()
            ->whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $organizer->marketplace_client_id))
            ->where('email', $validated['email'])
            ->where('status', 'active')
            ->where('id', '!=', $member->id)
            ->get()
            ->each(fn (MarketplaceOrganizerTeamMember $other) => $other->updateQuietly(['password' => $hashedPassword]));

        $shouldSendEmail = $validated['send_welcome_email'] ?? true;
        $emailSent = $shouldSendEmail
            ? $this->sendWelcomeEmail($member, $organizer, $validated['password'])
            : false;

        if (!$shouldSendEmail) {
            $message = 'Membru adăugat. Emailul cu credentialele NU a fost trimis (skip explicit).';
        } else {
            $message = $emailSent
                ? 'Membru adaugat. Email-ul cu credențialele a fost trimis.'
                : 'Membru adaugat, dar emailul nu a putut fi trimis. Comunica-i credentialele direct.';
        }

        $member->load('events:id');

        return $this->success([
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'leisure_role' => $member->leisure_role,
                'permissions' => $member->getEffectivePermissions(),
                'gate_id' => $member->gate_id,
                'status' => $member->status,
                'event_ids' => $member->role === 'admin' ? [] : $member->events->pluck('id')->all(),
            ],
            'email_sent' => $emailSent,
        ], $message);
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
            'role' => 'sometimes|in:admin,manager,staff',
            'leisure_role' => 'nullable|in:check_in,rental_boats,rental_pontoon,validation_pontoon,rental_sled,validation_tow,pos_cashier,admin_mobile,kiosk_selfcheckin',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:events,orders,reports,team,checkin',
            'gate_id' => 'nullable|integer',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:events,id',
        ]);

        // Can't update owner
        if (str_starts_with($validated['member_id'], 'owner_')) {
            return $this->error('Nu poti modifica proprietarul', 422);
        }

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        // Build only the fields that were actually sent — the gate-picker
        // submits gate_id only, the role-picker submits role+permissions only.
        $updates = [];

        if (array_key_exists('role', $validated)) {
            $permissions = $validated['permissions'] ?? [];
            if ($validated['role'] === 'admin') {
                $permissions = ['events', 'orders', 'reports', 'team', 'checkin'];
            }
            $updates['role'] = $validated['role'];
            $updates['permissions'] = $permissions;
        }

        if (array_key_exists('gate_id', $validated)) {
            $updates['gate_id'] = $validated['gate_id'];
        }

        if ($request->has('leisure_role')) {
            $updates['leisure_role'] = $validated['leisure_role'] ?? null;
        }

        if (!empty($updates)) {
            $member->update($updates);
        }

        // Event whitelist sync — only meaningful for non-admins. If event_ids
        // is explicitly sent (even as empty array), we treat it as authoritative.
        if ($request->has('event_ids')) {
            if ($member->role === 'admin') {
                // Admins ignore whitelist; clear any stale pivot rows.
                $member->events()->sync([]);
            } else {
                $allowed = Event::whereIn('id', $validated['event_ids'] ?? [])
                    ->where('marketplace_organizer_id', $organizer->id)
                    ->pluck('id')
                    ->all();
                $member->events()->sync($allowed);
            }
        }

        $member->load('events:id');

        return $this->success([
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'leisure_role' => $member->leisure_role,
                'permissions' => $member->getEffectivePermissions(),
                'gate_id' => $member->gate_id,
                'status' => $member->status,
                'event_ids' => $member->role === 'admin' ? [] : $member->events->pluck('id')->all(),
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
     * Activate a pending team member manually
     */
    /**
     * Reset an existing team member's password. Complements `activate` which
     * only works for still-pending members — this one is for active members
     * (e.g. staff forgot their password, or organizer wants to rotate it
     * from the mobile app without going through the email invite flow).
     *
     * Applies the same cross-organizer sync as `invite`: if the same email
     * exists on other active team memberships within the marketplace, the
     * new password is propagated so one login still works everywhere.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'member_id' => 'required|string',
            'password' => 'required|string|min:6|max:100',
        ]);

        if (str_starts_with($validated['member_id'], 'owner_')) {
            return $this->error('Nu poti reseta parola proprietarului', 422);
        }

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        $hashedPassword = bcrypt($validated['password']);
        $member->update(['password' => $hashedPassword]);

        // Cross-organizer sync — same email, other organizers, this marketplace
        MarketplaceOrganizerTeamMember::query()
            ->whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $organizer->marketplace_client_id))
            ->where('email', $member->email)
            ->where('status', 'active')
            ->where('id', '!=', $member->id)
            ->get()
            ->each(fn (MarketplaceOrganizerTeamMember $other) => $other->updateQuietly(['password' => $hashedPassword]));

        return $this->success([
            'member' => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ],
        ], 'Parola a fost resetata');
    }

    public function activate(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'member_id' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if (str_starts_with($validated['member_id'], 'owner_')) {
            return $this->error('Proprietarul este deja activ', 422);
        }

        $member = $organizer->teamMembers()->find($validated['member_id']);

        if (!$member) {
            return $this->error('Membrul nu a fost gasit', 404);
        }

        if ($member->status === 'active') {
            return $this->error('Membrul este deja activ', 422);
        }

        $member->update([
            'status' => 'active',
            'password' => bcrypt($validated['password']),
            'accepted_at' => now(),
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
        ], 'Cont activat cu succes');
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
        $emailSent = $this->sendInviteEmail($member, $organizer, $token);

        if (!$emailSent) {
            return $this->error('Emailul nu a putut fi trimis. Verificati configurarea serviciului de email.', 500);
        }

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
        $failed = 0;
        foreach ($pendingMembers as $member) {
            if ($member->canResendInvite()) {
                $token = $member->generateInviteToken();
                if ($this->sendInviteEmail($member, $organizer, $token)) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        if ($sent === 0 && $failed === 0) {
            return $this->error('Nu exista invitatii care pot fi retrimise in acest moment', 422);
        }

        if ($sent === 0 && $failed > 0) {
            return $this->error('Emailurile nu au putut fi trimise. Verificati configurarea serviciului de email.', 500);
        }

        $message = $sent . ' invitatii au fost retrimise';
        if ($failed > 0) {
            $message .= ' (' . $failed . ' au esuat)';
        }

        return $this->success([
            'sent_count' => $sent,
            'failed_count' => $failed,
        ], $message);
    }

    /**
     * Send invite email to team member using marketplace mail settings
     *
     * @return bool Whether the email was sent successfully
     */
    protected function sendInviteEmail(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, string $token): bool
    {
        $client = $organizer->marketplaceClient;
        $domain = preg_replace('#^https?://#', '', $client->domain);
        $inviteUrl = 'https://' . $domain . '/organizator/accept-invite?token=' . $token . '&email=' . urlencode($member->email);

        try {
            // Get marketplace mail transport
            $transport = $client->getMailTransport();

            if (!$transport) {
                \Log::warning('No mail transport configured for marketplace', [
                    'marketplace_id' => $client->id,
                    'marketplace_domain' => $client->domain,
                    'member_id' => $member->id,
                    'member_email' => $member->email,
                ]);
                return false;
            }

            // Get sender details using marketplace helper methods (includes legacy fallbacks)
            $fromEmail = $client->getEmailFromAddress();
            $fromName = $client->getEmailFromName() ?: $client->name;

            // Validate from email
            if (empty($fromEmail)) {
                \Log::error('No from email address configured for marketplace', [
                    'marketplace_id' => $client->id,
                    'marketplace_domain' => $client->domain,
                ]);
                return false;
            }

            \Log::info('Sending team invite email', [
                'marketplace_id' => $client->id,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to' => $member->email,
                'member_name' => $member->name,
            ]);

            // Build email using Symfony Mime
            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($member->email, $member->name))
                ->subject('Invitatie in echipa ' . $organizer->name)
                ->html($this->getInviteEmailHtml($member, $organizer, $client, $inviteUrl));

            // Send via marketplace transport
            $transport->send($email);

            \Log::info('Team invite email sent successfully', [
                'member_id' => $member->id,
                'member_email' => $member->email,
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to send team invite email', [
                'member_id' => $member->id,
                'member_email' => $member->email,
                'marketplace_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send a welcome email with the team member's login credentials and the
     * mobile app download link. Used by the new direct-add flow (admin sets
     * password directly instead of issuing an invite token).
     */
    protected function sendWelcomeEmail(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, string $plainPassword): bool
    {
        $client = $organizer->marketplaceClient;

        try {
            $transport = $client?->getMailTransport();
            if (!$transport) {
                \Log::warning('No mail transport configured for marketplace welcome email', [
                    'marketplace_id' => $client?->id,
                    'member_id' => $member->id,
                ]);
                return false;
            }

            $fromEmail = $client->getEmailFromAddress();
            $fromName = $client->getEmailFromName() ?: $client->name;
            if (empty($fromEmail)) {
                return false;
            }

            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($member->email, $member->name))
                ->subject('Bine ai venit in echipa ' . $organizer->name)
                ->html($this->getWelcomeEmailHtml($member, $organizer, $client, $plainPassword));

            $transport->send($email);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome email', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function getWelcomeEmailHtml(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, $marketplace, string $plainPassword): string
    {
        $appDownloadUrl = $marketplace?->mobile_app_download_url
            ?? rtrim((string) ($marketplace?->domain ?? 'https://ambilet.ro'), '/') . '/android';
        $marketplaceName = $marketplace?->name ?? 'AmBilet';
        $roleLabel = match ($member->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            default => 'Staff',
        };

        return '<!doctype html><html><body style="font-family: Arial, sans-serif; background:#f3f4f6; margin:0; padding:24px;">'
            . '<div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">'
            . '<h2 style="color:#111827; margin:0 0 16px;">Bun venit, ' . htmlspecialchars($member->name) . '!</h2>'
            . '<p style="color:#374151; line-height:1.6; margin:0 0 16px;">Ai fost adăugat în echipa <strong>' . htmlspecialchars($organizer->name) . '</strong> pe ' . htmlspecialchars($marketplaceName) . ' ca <strong>' . $roleLabel . '</strong>.</p>'
            . '<p style="color:#374151; line-height:1.6; margin:0 0 8px;">Te poți autentifica în aplicația mobilă cu următoarele date:</p>'
            . '<div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin:16px 0;">'
            . '<p style="margin:0 0 6px; color:#6b7280; font-size:13px;">Email</p>'
            . '<p style="margin:0 0 12px; color:#111827; font-weight:600;">' . htmlspecialchars($member->email) . '</p>'
            . '<p style="margin:0 0 6px; color:#6b7280; font-size:13px;">Parolă</p>'
            . '<p style="margin:0; color:#111827; font-weight:600; font-family: Menlo, monospace;">' . htmlspecialchars($plainPassword) . '</p>'
            . '</div>'
            . '<p style="color:#374151; line-height:1.6; margin:0 0 20px;">După prima autentificare, poți schimba parola din ecranul Setări.</p>'
            . '<a href="' . htmlspecialchars($appDownloadUrl) . '" style="display:inline-block; background:#7c3aed; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:600;">Descarcă aplicația</a>'
            . '<p style="color:#9ca3af; font-size:12px; margin:24px 0 0;">Dacă nu te-ai așteptat la acest email, poți să-l ignori — contul nu va fi activ fără autentificarea ta.</p>'
            . '</div></body></html>';
    }

    /**
     * Get HTML for invite email
     */
    protected function getInviteEmailHtml(MarketplaceOrganizerTeamMember $member, MarketplaceOrganizer $organizer, $marketplace, string $inviteUrl): string
    {
        $roleLabel = match ($member->role) {
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            default => ucfirst($member->role),
        };
        $marketplaceName = $marketplace->name ?? $marketplace->domain;

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

        <p>Ai fost invitat în echipa <strong>{$organizer->name}</strong> ca <strong>{$roleLabel}</strong> pe platforma <strong>{$marketplaceName}</strong>.</p>

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

    /**
     * Public: Validate an invite token and return invitation details.
     * No authentication required — called from the accept-invite page.
     */
    public function validateInvite(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $member = MarketplaceOrganizerTeamMember::whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('email', $validated['email'])
            ->where('status', 'pending')
            ->first();

        if (!$member || !$member->verifyInviteToken($validated['token'])) {
            return $this->error('Invitația este invalidă sau a expirat.', 400);
        }

        $organizer = $member->organizer;

        // Check if this email already has a password set on another active team member
        // (from a previous invite acceptance). If so, frontend can skip the password form.
        $hasExistingPassword = $this->findExistingPasswordHash($validated['email'], $client->id, $member->id) !== null;

        return $this->success([
            'member' => [
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
            ],
            'organizer' => [
                'name' => $organizer->name,
                'company_name' => $organizer->company_name,
            ],
            'has_existing_password' => $hasExistingPassword,
        ]);
    }

    /**
     * Public: Accept an invite — set password and activate membership.
     * If the email already has a password set on another team member record
     * (from a previous invite), the password form is skipped and the hash
     * is copied from the existing record.
     * No authentication required.
     */
    public function acceptInvitePublic(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Base validation — token and email always required
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:30',
        ]);

        $email = $request->input('email');
        $token = $request->input('token');
        $phone = $request->input('phone');

        $member = MarketplaceOrganizerTeamMember::whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if (!$member || !$member->verifyInviteToken($token)) {
            return $this->error('Invitația este invalidă sau a expirat.', 400);
        }

        // Check if email already has a password hash on another active team member
        $existingHash = $this->findExistingPasswordHash($email, $client->id, $member->id);

        if ($existingHash !== null) {
            // Reuse existing password hash — no new password required
            $member->update([
                'password' => $existingHash,
                'status' => 'active',
                'invite_token' => null,
                'invite_expires_at' => null,
                'accepted_at' => now(),
            ]);
        } else {
            // First-time acceptance — require password
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $member->acceptInvite($request->input('password'));
        }

        if (!empty($phone)) {
            $member->update(['phone' => $phone]);
        }

        return $this->success([
            'message' => 'Contul a fost activat cu succes!',
            'organizer_name' => $member->organizer->name,
            'reused_existing_password' => $existingHash !== null,
        ]);
    }

    /**
     * Find an existing password hash for the given email on another active team member
     * record within the same marketplace. Returns null if none found.
     */
    protected function findExistingPasswordHash(string $email, int $marketplaceClientId, int $excludeMemberId): ?string
    {
        $existing = MarketplaceOrganizerTeamMember::whereHas('organizer', fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId))
            ->where('email', $email)
            ->where('status', 'active')
            ->whereNotNull('password')
            ->where('id', '!=', $excludeMemberId)
            ->first();

        return $existing?->password;
    }
}
