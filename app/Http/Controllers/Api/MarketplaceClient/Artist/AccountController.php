<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceArtistAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password as PasswordRules;

/**
 * Account-management endpoints (settings page on ambilet).
 * Splits cleanly from ArtistAuthController so register/login/forgot
 * stay focused while account self-service has its own home.
 */
class AccountController extends BaseController
{
    /**
     * PUT /artist/account — update non-credential profile fields on the
     * account itself (the things on the Settings page that don't touch
     * the public Artist record). Email is NOT updated here (see
     * updateEmail) because changing it requires re-verification.
     */
    public function update(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:50',
            'locale' => 'sometimes|nullable|in:ro,en,de,fr,es',
        ]);

        $account->update($validated);

        return $this->success([
            'account' => $this->formatAccount($account->fresh()),
        ], 'Setări salvate.');
    }

    /**
     * PUT /artist/account/password — change password (requires current).
     * On success, revokes all OTHER tokens so concurrent sessions on
     * other devices are forced to re-auth, but keeps the current token
     * so the user isn't kicked out of the page they're on.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $account->password)) {
            return $this->error('Parola curentă este incorectă.', 422, ['code' => 'wrong_current_password']);
        }

        $account->update(['password' => Hash::make($validated['password'])]);

        // Revoke all other tokens; keep this one so the response can return.
        $currentTokenId = $request->user()->currentAccessToken()?->id;
        if ($currentTokenId) {
            $account->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return $this->success(null, 'Parola a fost schimbată.');
    }

    /**
     * DELETE /artist/account — soft-delete the account. Requires password
     * confirmation. Revokes all tokens. Does NOT touch the linked Artist
     * record itself (the public profile stays; only the claim is removed).
     */
    public function destroy(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($validated['password'], $account->password)) {
            return $this->error('Parolă incorectă.', 422, ['code' => 'wrong_password']);
        }

        try {
            Log::channel('marketplace')->info('Artist account self-deleted', [
                'artist_account_id' => $account->id,
                'marketplace_client_id' => $account->marketplace_client_id,
                'email' => $account->email,
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // logging must never block deletion
        }

        $account->tokens()->delete();
        $account->delete(); // soft-delete (model uses SoftDeletes)

        return $this->success(null, 'Cont șters.');
    }

    /**
     * GET /artist/account — currently authenticated account info (for the
     * Settings page initial render). Same shape as auth.me but kept as a
     * dedicated path so cache/permissions can diverge later.
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof MarketplaceArtistAccount) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'account' => $this->formatAccount($account),
        ]);
    }

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
}
