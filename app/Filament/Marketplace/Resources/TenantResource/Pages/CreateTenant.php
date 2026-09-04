<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Enums\TenantType;
use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;


    /**
     * Everything happens inside handleRecordCreation because it's the
     * single Filament hook that receives $data directly. Splitting the
     * work between mutateFormDataBeforeCreate (extract to class prop)
     * and afterCreate (read class prop) hits a Livewire lifecycle bug:
     * class properties get re-hydrated between hooks and reset to their
     * default, so the class prop we set in mutate reads back as [] in
     * afterCreate — which is exactly what happened on tenant 38 (user
     * created with blank name+email, zero venues linked). Doing it all
     * in one hook + one transaction removes that fragility entirely.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // $data here is post-dehydration (owner_* + linked_venue_ids
        // fields were declared dehydrated(false) so they're stripped
        // before Filament fills the model). Read the raw form state
        // from $this->data instead — those keys ARE still there because
        // Filament keeps the whole form state on the component while
        // only $data is used for the model insert.
        $formState = $this->data ?? [];

        $mcId = TenantResource::getMarketplaceClient()?->id;
        if (!$mcId) {
            throw ValidationException::withMessages([
                'public_name' => 'Marketplace context lipsă — reautentifică-te.',
            ]);
        }

        $firstName = trim((string) ($formState['owner_first_name'] ?? ''));
        $lastName = trim((string) ($formState['owner_last_name'] ?? ''));
        $email = strtolower(trim((string) ($formState['owner_email'] ?? '')));
        $password = (string) ($formState['owner_password'] ?? '');
        $venueIds = is_array($formState['linked_venue_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $formState['linked_venue_ids'])))
            : [];

        if ($email === '' || $password === '') {
            throw ValidationException::withMessages([
                'owner_email' => 'Email + parolă obligatorii pentru contul owner.',
            ]);
        }

        // Email collision — any existing users row (organizer, artist,
        // editor, another tenant owner) means we refuse. Linking to an
        // existing user is deliberately off-scope for the marketplace
        // flow per the 2026-08-22 spec.
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $role = $existing->role ?? 'necunoscut';
            $ownedTenant = Tenant::where('owner_id', $existing->id)->first();
            $usage = $ownedTenant
                ? "cont tenant existent \"" . ($ownedTenant->public_name ?? $ownedTenant->name) . '"'
                : "cont existent cu rol \"{$role}\"";
            throw ValidationException::withMessages([
                'owner_email' => "Adresa {$email} este deja folosită de un {$usage}. Alege alt email — linkarea la un cont existent nu e suportată din marketplace panel.",
            ]);
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $publicName = trim((string) ($data['public_name'] ?? '')) ?: ($fullName ?: 'Venue Owner');

        $tenant = DB::transaction(function () use ($data, $mcId, $email, $password, $fullName, $publicName, $venueIds) {
            // 1) Tenant. tenant_type + origin marker are re-stamped
            //    server-side even if the hidden fields were tampered
            //    with. Slug is unique-suffixed against existing rows.
            $baseSlug = Str::slug($publicName);
            $slug = $baseSlug;
            $i = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . (++$i);
            }

            $tenant = Tenant::create([
                'name' => $fullName !== '' ? $fullName : $publicName,
                'public_name' => $publicName,
                'slug' => $slug,
                'locale' => $data['locale'] ?? 'ro',
                'tenant_type' => TenantType::Venue->value,
                'status' => 'active',
                'created_by_marketplace_client_id' => $mcId,
            ]);

            // 2) Owner user. Prod users table has only `name` (no
            //    first_name/last_name — see project_users_schema_drift).
            //    Concat here; the form's two-field UI stays intact.
            $owner = User::create([
                'name' => $fullName !== '' ? $fullName : $email,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'tenant',
                'tenant_id' => $tenant->id,
                'marketplace_client_id' => $mcId,
                'email_verified_at' => now(),
            ]);

            $tenant->owner_id = $owner->id;
            $tenant->save();

            // 3) Link the venues. Whole DB catalog per operator request
            //    (2026-08-22) — no marketplace_client_id filter.
            if (!empty($venueIds)) {
                Venue::whereIn('id', $venueIds)->update(['tenant_id' => $tenant->id]);
            }

            return $tenant;
        });

        // Best-effort welcome email with a 48h one-time set-password link.
        // Failure here must never block account creation (the tenant + owner
        // already committed above), so it is wrapped defensively.
        try {
            $this->sendVenueOwnerWelcomeEmail($mcId, $email, $fullName, $publicName);
        } catch (\Throwable $e) {
            \Log::channel('marketplace')->warning('Failed to send venue-owner welcome email', [
                'tenant_id' => $tenant->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return $tenant;
    }

    /**
     * Send the venue owner a welcome email containing a one-time, 48h link to
     * set their own password (no plaintext password is ever emailed) plus a
     * summary of what the account can do. Sent via the marketplace's own mail
     * transport so the From address matches the marketplace.
     */
    protected function sendVenueOwnerWelcomeEmail(int $mcId, string $email, string $fullName, string $publicName): void
    {
        $client = \App\Models\MarketplaceClient::find($mcId);
        if (!$client) {
            return;
        }

        // One-time token stored hashed in password_reset_tokens (validated with
        // a 48h window by VenueOwnerAuthController::setPassword).
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $domain = rtrim((string) ($client->domain ?? ''), '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        $setUrl = $domain . '/proprietar/seteaza-parola?token=' . $token . '&email=' . urlencode($email);
        $appUrl = $domain . '/android';
        $siteName = $client->name ?? 'AmBilet';
        $firstName = trim(explode(' ', $fullName)[0] ?? '') ?: 'Bună';

        // Capabilities of a venue-owner account inside the AmBilet mobile app.
        $capabilities = [
            'Scanezi biletele și faci check-in participanților la intrare',
            'Vinzi bilete pe loc prin POS (casă de marcat în aplicație)',
            'Urmărești rapoarte de vânzări și încasări',
            'Gestionezi porțile / punctele de acces',
            'Asignezi și administrezi personalul de scanare',
        ];
        $capsHtml = '<ul style="margin:0;padding-left:20px;color:#475569;font-size:14px;line-height:1.9">'
            . implode('', array_map(fn ($c) => '<li>' . htmlspecialchars($c) . '</li>', $capabilities))
            . '</ul>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Contul tău de administrare locație</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '!</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Ți-a fost creat un cont de administrare pentru <strong>' . htmlspecialchars($publicName) . '</strong> pe ' . htmlspecialchars($siteName) . '. Emailul tău de autentificare este <strong>' . htmlspecialchars($email) . '</strong>.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 20px">Pas 1 — setează-ți parola:</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($setUrl) . '" style="display:inline-block;background:#4f46e5;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează-ți parola</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:0 0 24px;text-align:center">Linkul este valabil 48 de ore și poate fi folosit o singură dată.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 12px">Pas 2 — descarcă aplicația <strong>AmBilet</strong> și autentifică-te cu emailul și parola ta:</p>'
            . '<div style="text-align:center;margin:16px 0 24px">'
            . '<a href="' . htmlspecialchars($appUrl) . '" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:15px">Descarcă aplicația AmBilet</a>'
            . '</div>'
            . '<div style="border-top:1px solid #e2e8f0;padding-top:20px">'
            . '<p style="font-size:15px;color:#1e293b;font-weight:600;margin:0 0 10px">Ce poți face din aplicație:</p>'
            . $capsHtml
            . '</div>'
            . '<p style="font-size:14px;color:#475569;margin:20px 0 0">Aplicația AmBilet o găsești oricând la <a href="' . htmlspecialchars($appUrl) . '" style="color:#4f46e5">' . htmlspecialchars($appUrl) . '</a>.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
            $client,
            $email,
            $fullName !== '' ? $fullName : $email,
            'Contul tău de administrare locație — setează-ți parola',
            $html,
            ['template_slug' => 'venue_owner_welcome']
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Venue owner creat')
            ->body('Cont creat + locațiile linked. I s-a trimis un email cu link de setare a parolei (valabil 48h) și lista a ce poate face.')
            ->success();
    }
}
