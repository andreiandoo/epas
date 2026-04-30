<?php

namespace App\Services\ArtistAccount;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Centralizes the admin-side lifecycle transitions for an artist account
 * (approve / reject / suspend / reactivate / link-artist) plus the email
 * notifications that go out on each. Filament actions delegate here so the
 * same flow can be replayed from CLI / API / tests without duplicating logic.
 */
class ArtistAccountApprovalService
{
    /**
     * Approve a pending account. Sets status=active, links the approving
     * admin, clears any prior rejection, and emails the applicant.
     *
     * Returns false (without throwing) if email is not yet verified — admin
     * shouldn't be able to approve unverified accounts; the Filament action
     * already gates this, but we double-check defensively.
     */
    public function approve(MarketplaceArtistAccount $account, User $admin): bool
    {
        if (!$account->isEmailVerified()) {
            return false;
        }

        $account->markApproved($admin);

        $client = $account->marketplaceClient;
        if ($client) {
            try {
                $this->sendApprovedEmail($client, $account->fresh());
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Failed to send artist account approval email', [
                    'artist_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Reject a pending account with a required human-readable reason.
     */
    public function reject(MarketplaceArtistAccount $account, string $reason): void
    {
        $account->markRejected($reason);

        $client = $account->marketplaceClient;
        if ($client) {
            try {
                $this->sendRejectedEmail($client, $account->fresh(), $reason);
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Failed to send artist account rejection email', [
                    'artist_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function suspend(MarketplaceArtistAccount $account): void
    {
        $account->markSuspended();

        // Revoke all active Sanctum tokens so suspended accounts cannot
        // continue using a previously-issued token.
        $account->tokens()->delete();
    }

    public function reactivate(MarketplaceArtistAccount $account): void
    {
        $account->markReactivated();
    }

    /**
     * Manually associate an Artist content profile with an account that
     * registered without claiming one (or unlink an incorrect claim).
     */
    public function linkArtist(MarketplaceArtistAccount $account, ?int $artistId): void
    {
        if ($artistId !== null) {
            $artist = Artist::find($artistId);
            if (!$artist) {
                return;
            }
        }

        $account->update(['artist_id' => $artistId]);
    }

    // =========================================
    // Email helpers
    // =========================================

    protected function sendApprovedEmail(MarketplaceClient $client, MarketplaceArtistAccount $account): void
    {
        $domain = rtrim($client->domain, '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $loginUrl = $domain . '/artist/login';
        $dashboardUrl = $domain . '/artist/cont/dashboard';
        $firstName = $account->first_name ?: 'Artist';
        $siteName = $client->name ?? 'ambilet.ro';
        $artistName = $account->artist?->name ?? null;

        $linkedLine = $artistName
            ? '<p style="font-size:15px;color:#475569;margin:0 0 16px">Profilul de artist <strong>' . htmlspecialchars($artistName) . '</strong> este acum asociat contului tău și îl poți edita din dashboard.</p>'
            : '<p style="font-size:15px;color:#475569;margin:0 0 16px">Echipa noastră va asocia profilul tău de artist în scurt timp. Vei putea edita informațiile imediat după.</p>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#16A34A 0%,#15803D 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Cont aprobat 🎉</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '!</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Vești bune — contul tău de artist pe ' . htmlspecialchars($siteName) . ' a fost aprobat.</p>'
            . $linkedLine
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block;background:#16A34A;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Conectează-te</a>'
            . '</div>'
            . '<p style="font-size:14px;color:#475569;margin:16px 0 0">După conectare, intră în <a href="' . htmlspecialchars($dashboardUrl) . '" style="color:#16A34A">dashboard</a> pentru a-ți gestiona profilul, evenimentele și setările contului.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        BaseController::sendViaMarketplace(
            $client,
            $account->email,
            $firstName,
            'Contul tău de artist a fost aprobat',
            $html,
            ['template_slug' => 'artist_account_approved']
        );
    }

    protected function sendRejectedEmail(MarketplaceClient $client, MarketplaceArtistAccount $account, string $reason): void
    {
        $domain = rtrim($client->domain, '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $supportEmail = config('mail.from.address') ?: ('contact@' . preg_replace('/^https?:\/\//', '', $domain));
        $firstName = $account->first_name ?: 'Artist';
        $siteName = $client->name ?? 'ambilet.ro';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#475569 0%,#334155 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Cerere cont artist</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Îți mulțumim că ai aplicat pentru un cont de artist pe ' . htmlspecialchars($siteName) . '. Din păcate, cererea ta nu a putut fi aprobată în această etapă.</p>'
            . '<div style="background:#f1f5f9;border-left:4px solid #475569;padding:16px;border-radius:8px;margin:16px 0">'
            . '<p style="font-size:14px;color:#334155;margin:0 0 4px;font-weight:600">Motiv:</p>'
            . '<p style="font-size:14px;color:#475569;margin:0;white-space:pre-wrap">' . htmlspecialchars($reason) . '</p>'
            . '</div>'
            . '<p style="font-size:14px;color:#475569;margin:16px 0 0">Dacă ai întrebări sau crezi că este o eroare, ne poți contacta la <a href="mailto:' . htmlspecialchars($supportEmail) . '" style="color:#475569">' . htmlspecialchars($supportEmail) . '</a>.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        BaseController::sendViaMarketplace(
            $client,
            $account->email,
            $firstName,
            'Cerere cont artist — actualizare',
            $html,
            ['template_slug' => 'artist_account_rejected']
        );
    }
}
