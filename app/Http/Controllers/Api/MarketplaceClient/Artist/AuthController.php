<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceClient;
use App\Support\SearchHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;

class AuthController extends BaseController
{
    /**
     * Register a new artist account. Optionally claims an existing Artist profile
     * by slug. Account starts in `pending` status; the applicant must verify email
     * and a marketplace admin must approve before login is allowed.
     */
    public function register(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // The applicant MUST identify which artist they represent — either via
        // slug (the "Revendică profilul" CTA on /artist/{slug}) or via id from
        // the on-form picker. claim_proof was removed entirely (Etapa 4
        // feedback): copy-pasting social links isn't real validation.
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|max:5',
            'artist_slug' => 'nullable|string|max:190|required_without:artist_id',
            'artist_id' => 'nullable|integer|required_without:artist_slug',
            'claim_message' => 'nullable|string|max:2000',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        // Per-marketplace email uniqueness — same email can register on multiple marketplaces.
        if (MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->where('email', $email)
            ->exists()) {
            return $this->error('Există deja un cont de artist cu această adresă de email.', 422);
        }

        // Resolve the claimed artist by either slug or id.
        $artist = null;
        if (!empty($validated['artist_id'])) {
            $artist = Artist::find($validated['artist_id']);
        } elseif (!empty($validated['artist_slug'])) {
            $artist = Artist::where('slug', $validated['artist_slug'])->first();
        }

        if (!$artist) {
            return $this->error('Profilul de artist solicitat nu a fost găsit.', 404);
        }

        // Block claiming a profile that is already pending or active for this marketplace.
        $alreadyClaimed = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->where('artist_id', $artist->id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($alreadyClaimed) {
            return $this->error('Acest profil de artist a fost deja revendicat.', 422);
        }

        $account = MarketplaceArtistAccount::create([
            'marketplace_client_id' => $client->id,
            'artist_id' => $artist->id,
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'locale' => $validated['locale'] ?? 'ro',
            'status' => 'pending',
            'claim_message' => $validated['claim_message'] ?? null,
            'claim_proof' => null,
            'claim_submitted_at' => now(),
        ]);

        // Send verification email via marketplace transport (best-effort).
        $verificationToken = $account->generateEmailVerificationToken();
        try {
            $this->sendArtistVerificationEmail($client, $account, $verificationToken);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Failed to send artist verification email', [
                'artist_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success([
            'account' => $this->formatAccount($account),
            'requires_verification' => true,
            'requires_approval' => true,
        ], 'Cont creat cu succes. Verifică-ți emailul, apoi cererea ta intră în review.', 201);
    }

    /**
     * Login an artist account. Enforces three gates in order:
     *   1. credentials valid
     *   2. email verified
     *   3. status = 'active' (admin-approved)
     * Returns a Sanctum token only when all three pass.
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $account = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$account || !Hash::check($validated['password'], $account->password)) {
            try {
                Log::channel('security')->info('artist account login failed', [
                    'client_id' => $client->id,
                    'email' => $email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => $account ? 'pw_mismatch' : 'email_not_found',
                ]);
            } catch (\Throwable $e) {
                // logging must never block login response
            }
            return $this->error('Email sau parolă incorectă.', 401);
        }

        if (!$account->isEmailVerified()) {
            return $this->error('Adresa de email nu este verificată. Te rugăm să verifici linkul primit pe email.', 403, [
                'code' => 'email_not_verified',
            ]);
        }

        if ($account->isPending()) {
            return $this->error('Cererea ta este încă în review. Vei primi un email când contul va fi aprobat.', 403, [
                'code' => 'pending_approval',
            ]);
        }

        if ($account->isRejected()) {
            return $this->error('Cererea ta a fost respinsă.', 403, [
                'code' => 'rejected',
                'reason' => $account->rejection_reason,
            ]);
        }

        if ($account->isSuspended()) {
            return $this->error('Contul tău a fost suspendat.', 403, [
                'code' => 'suspended',
            ]);
        }

        $account->recordLogin();

        $token = $account->createToken('artist-api')->plainTextToken;

        return $this->success([
            'account' => $this->formatAccount($account),
            'token' => $token,
        ], 'Autentificare reușită.');
    }

    /**
     * Logout — revoke the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Deconectat cu succes.');
    }

    /**
     * Get the currently authenticated artist account.
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'account' => $this->formatAccount($account),
        ]);
    }

    /**
     * Send a password reset link. Always returns success to prevent
     * email enumeration.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $account = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $genericResponse = $this->success(null, 'Dacă există un cont cu acest email, vei primi un link de resetare.');

        if (!$account) {
            return $genericResponse;
        }

        // Wipe any prior tokens for this account (across normal and bulk types).
        DB::table('marketplace_password_resets')
            ->where('email', $account->email)
            ->whereIn('type', ['artist', 'bulk_artist'])
            ->where('marketplace_client_id', $client->id)
            ->delete();

        $token = Str::random(64);
        DB::table('marketplace_password_resets')->insert([
            'email' => $account->email,
            'type' => 'artist',
            'marketplace_client_id' => $client->id,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        try {
            $this->sendArtistPasswordResetEmail($client, $account, $token);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Failed to send artist password reset email', [
                'artist_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $genericResponse;
    }

    /**
     * Reset password with a valid token. Revokes all existing API tokens
     * on success so concurrent sessions are forced to re-auth.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        $records = DB::table('marketplace_password_resets')
            ->where('email', $validated['email'])
            ->whereIn('type', ['artist', 'bulk_artist'])
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at')
            ->get();

        if ($records->isEmpty()) {
            return $this->error('Token de resetare invalid sau expirat.', 400);
        }

        $record = null;
        foreach ($records as $candidate) {
            if (Hash::check($validated['token'], $candidate->token)) {
                $record = $candidate;
                break;
            }
        }

        if (!$record) {
            return $this->error('Token de resetare invalid sau expirat.', 400);
        }

        // Bulk tokens get 7 days, normal tokens 60 minutes. Use abs() — Carbon 3
        // returns signed minutes, so a backwards-clock skew can otherwise read
        // as a huge negative number and bypass the check.
        $isBulkToken = str_starts_with($record->type, 'bulk_');
        $maxMinutes = $isBulkToken ? 10080 : 60;
        if (abs(now()->diffInMinutes($record->created_at)) > $maxMinutes) {
            DB::table('marketplace_password_resets')->where('id', $record->id)->delete();
            return $this->error('Token de resetare expirat.', 400);
        }

        $account = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$account) {
            return $this->error('Cont negăsit.', 404);
        }

        $account->update([
            'password' => Hash::make($validated['password']),
        ]);

        DB::table('marketplace_password_resets')->where('id', $record->id)->delete();
        $account->tokens()->delete();

        return $this->success(null, 'Parola a fost resetată cu succes. Te rugăm să te conectezi cu noua parolă.');
    }

    /**
     * Verify an email address with the supplied token. The account remains
     * `pending` until an admin approves it; this only flips email_verified_at.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $account = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$account) {
            return $this->error('Cont negăsit.', 404);
        }

        if ($account->isEmailVerified()) {
            return $this->success([
                'account' => $this->formatAccount($account),
            ], 'Emailul este deja verificat.');
        }

        if (!$account->verifyEmailWithToken($validated['token'])) {
            if ($account->isVerificationTokenExpired()) {
                return $this->error('Linkul de verificare a expirat. Te rugăm să soliciți unul nou.', 400);
            }
            return $this->error('Token de verificare invalid.', 400);
        }

        return $this->success([
            'account' => $this->formatAccount($account->fresh()),
        ], 'Email verificat cu succes. Cererea ta este în review.');
    }

    /**
     * Resend the email verification link. Rate-limited to once per minute.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        $account = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $genericResponse = $this->success(null, 'Dacă există un cont cu acest email, un link de verificare a fost trimis.');

        if (!$account) {
            return $genericResponse;
        }

        if ($account->isEmailVerified()) {
            return $this->success(null, 'Emailul este deja verificat.');
        }

        if (!$account->canResendVerification()) {
            return $this->error('Te rugăm să aștepți câteva momente înainte de a solicita un alt email de verificare.', 429);
        }

        $verificationToken = $account->generateEmailVerificationToken();
        try {
            $this->sendArtistVerificationEmail($client, $account, $verificationToken);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Failed to resend artist verification email', [
                'artist_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success(null, 'Email de verificare trimis.');
    }

    /**
     * Lightweight artist search for the register-page picker. Returns up to
     * 20 matches scoped to artists that are partners of the calling
     * marketplace_client (so applicants can only claim profiles that
     * actually exist on this marketplace). Public — no user auth required.
     */
    public function searchArtists(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = trim((string) $request->query('q', ''));

        // Empty queries return featured/popular artists so the picker has
        // something to show before the user types.
        $artists = Artist::query()
            ->whereHas('marketplaceClients', function ($q) use ($client) {
                $q->where('marketplace_artist_partners.marketplace_client_id', $client->id);
            })
            ->when($query !== '', function ($q) use ($query) {
                // Case- AND diacritic-insensitive across name and slug.
                // SearchHelper folds Romanian diacritics on both sides
                // ("Iași" / "iasi" / "IAȘI" all match the same row).
                $q->where(function ($qq) use ($query) {
                    $qq->where(function ($name) use ($query) {
                            SearchHelper::search($name, 'name', $query);
                        })
                        ->orWhere(function ($slug) use ($query) {
                            SearchHelper::search($slug, 'slug', $query);
                        });
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'logo_url', 'main_image_url']);

        // Mark artists that already have an active or pending claim so the
        // picker can grey them out — applicants can't double-claim.
        $claimedArtistIds = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->whereIn('status', ['pending', 'active'])
            ->whereIn('artist_id', $artists->pluck('id'))
            ->pluck('artist_id')
            ->all();

        return $this->success([
            'artists' => $artists->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'logo_url' => $a->logo_url,
                'main_image_url' => $a->main_image_url,
                'is_claimed' => in_array($a->id, $claimedArtistIds, true),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Check whether an Artist profile slug has already been claimed for this
     * marketplace. Powers the "Revendică profilul" / "Profil verificat" toggle
     * on the public artist page. Public — no auth required.
     */
    public function checkClaim(Request $request, string $artistSlug): JsonResponse
    {
        $client = $this->requireClient($request);

        $artist = Artist::where('slug', $artistSlug)->first();

        if (!$artist) {
            return $this->success([
                'exists' => false,
                'is_claimed' => false,
                'is_verified' => false,
            ]);
        }

        $activeClaim = MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
            ->where('artist_id', $artist->id)
            ->where('status', 'active')
            ->exists();

        $pendingClaim = $activeClaim
            ? false
            : MarketplaceArtistAccount::where('marketplace_client_id', $client->id)
                ->where('artist_id', $artist->id)
                ->where('status', 'pending')
                ->exists();

        return $this->success([
            'exists' => true,
            'is_claimed' => $activeClaim || $pendingClaim,
            'is_verified' => $activeClaim,
            'is_pending' => $pendingClaim,
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Format an artist account for API responses. Includes a thin slice of the
     * linked Artist record (logo, name, slug) so the frontend can render the
     * dashboard header without a second round-trip.
     */
    protected function formatAccount(MarketplaceArtistAccount $account): array
    {
        $account->loadMissing('artist:id,name,slug,logo_url,main_image_url');

        return [
            'id' => $account->id,
            'email' => $account->email,
            'first_name' => $account->first_name,
            'last_name' => $account->last_name,
            'full_name' => $account->full_name,
            'phone' => $account->phone,
            'locale' => $account->locale,
            'status' => $account->status,
            'is_email_verified' => $account->isEmailVerified(),
            'can_edit_profile' => $account->canEditArtistProfile(),
            'rejection_reason' => $account->rejection_reason,
            'artist' => $account->artist ? [
                'id' => $account->artist->id,
                'name' => $account->artist->name,
                'slug' => $account->artist->slug,
                'logo_url' => $account->artist->logo_url,
                'main_image_url' => $account->artist->main_image_url,
            ] : null,
            'created_at' => $account->created_at->toIso8601String(),
        ];
    }

    /**
     * Send the email-verification email via the marketplace's transport
     * (so the From: address matches the marketplace, not Tixello core).
     */
    protected function sendArtistVerificationEmail(MarketplaceClient $client, MarketplaceArtistAccount $account, string $token): void
    {
        $domain = rtrim($client->domain, '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $verifyUrl = sprintf('%s/artist/verifica-email?token=%s&email=%s', $domain, $token, urlencode($account->email));
        $firstName = $account->first_name ?: 'Artist';
        $siteName = $client->name ?? 'ambilet.ro';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Verifică adresa de email</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '!</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 20px">Mulțumim pentru cererea de cont artist pe ' . htmlspecialchars($siteName) . '. Pentru a continua, te rugăm să-ți confirmi adresa de email.</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($verifyUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Verifică adresa de email</a>'
            . '</div>'
            . '<p style="font-size:14px;color:#475569;margin:16px 0 0">După verificare, cererea ta intră automat în review. Echipa noastră va analiza informațiile și te va anunța prin email când contul tău va fi aprobat.</p>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul de verificare expiră în 24 de ore.</p>'
            . '<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;text-align:center">Dacă nu ai solicitat un cont, poți ignora acest email.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $account->email, $firstName, 'Verifică adresa de email — cont artist', $html, [
            'template_slug' => 'artist_email_verification',
        ]);
    }

    /**
     * Send a password reset link via the marketplace's transport.
     */
    protected function sendArtistPasswordResetEmail(MarketplaceClient $client, MarketplaceArtistAccount $account, string $token): void
    {
        $domain = $client->domain ? rtrim($client->domain, '/') : config('app.url');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $resetUrl = $domain . '/artist/resetare-parola?' . http_build_query([
            'token' => $token,
            'email' => $account->email,
        ]);
        $firstName = $account->first_name ?: 'Artist';
        $siteName = $client->name ?? 'ambilet.ro';
        $expireMinutes = 60;

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Resetare parolă</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut ' . htmlspecialchars($firstName) . ',</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Ai primit acest email deoarece am primit o cerere de resetare a parolei pentru contul tău de artist.</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Resetează parola</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul expiră în ' . $expireMinutes . ' de minute.</p>'
            . '<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;text-align:center">Dacă nu ai solicitat resetarea parolei, nu este necesară nicio acțiune.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $account->email, $firstName, 'Resetare parolă — cont artist', $html, [
            'template_slug' => 'artist_password_reset',
        ]);
    }
}
