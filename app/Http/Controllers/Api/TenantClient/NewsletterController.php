<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\TenantNewsletterSubscriber;
use App\Services\TenantMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Newsletterul tenantului, pentru site-urile de tenant:
 *   POST /tenant-client/newsletter/subscribe     {email, first_name?, locale?, consent: true}
 *   POST /tenant-client/newsletter/unsubscribe   {token}
 * Abonarea cere acordul explicit (consent) și trimite un email de bun venit cu linkul
 * de dezabonare. Răspunsul e același dacă adresa era deja abonată.
 */
class NewsletterController extends Controller
{
    use ResolvesTenant;

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:190',
            'first_name' => 'nullable|string|max:100',
            'locale' => 'nullable|in:ro,en',
            'consent' => 'accepted',
        ]);
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];
        $locale = $data['locale'] ?? 'ro';
        $email = mb_strtolower(trim($data['email']));

        $wasActive = TenantNewsletterSubscriber::where('tenant_id', $tenant->id)->where('email', $email)->where('status', 'subscribed')->exists();
        $sub = TenantNewsletterSubscriber::subscribe($tenant, $email, [
            'first_name' => trim((string) ($data['first_name'] ?? '')) ?: null,
            'locale' => $locale, 'source' => 'site', 'consent_ip' => $request->header('X-Visitor-IP') ?: $request->ip(),
        ]);
        if (! $wasActive) {
            $this->welcome($tenant, $sub);
        }

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:64']);
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $sub = TenantNewsletterSubscriber::where('tenant_id', $resolved['tenant']->id)->where('token', $data['token'])->first();
        if (! $sub) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }
        $sub->unsubscribe();

        return response()->json(['success' => true, 'email' => $this->mask($sub->email)]);
    }

    /** Emailul de bun venit: confirmă abonarea și arată de la început cum te dezabonezi. */
    private function welcome($tenant, TenantNewsletterSubscriber $sub): void
    {
        try {
            $en = $sub->locale === 'en';
            $name = $tenant->public_name ?: $tenant->name;
            $site = DemoStorefrontController::siteUrl($tenant);
            $hello = $sub->first_name ? ($en ? 'Hi ' : 'Salut, ') . e($sub->first_name) . '!' : ($en ? 'Hi!' : 'Salut!');
            $body = '<p style="margin:0 0 14px;font-size:16px;color:#1B2140;line-height:1.6;">' . $hello . '</p>'
                . '<p style="margin:0;font-size:16px;color:#1B2140;line-height:1.6;">' . ($en
                    ? 'You’re on the list. You’ll be the first to hear about new dates, ticket sales and news from ' . e($name) . '.'
                    : 'Ești pe listă. Afli primul de datele noi, de punerea în vânzare a biletelor și de noutățile ' . e($name) . '.') . '</p>';
            $unsub = $site ? $site . '/dezabonare?token=' . urlencode($sub->token) . ($en ? '&lang=en' : '') : null;
            $html = DemoStorefrontController::mailLayout($tenant, $en ? 'Welcome to the list' : 'Bine ai venit pe listă', $body,
                $site ? ($en ? 'Visit the website' : 'Vezi site-ul') : null, $site, '',
                $unsub ? ($en ? 'Don’t want these emails? ' : 'Nu mai vrei aceste emailuri? ') . '<a href="' . e($unsub) . '" style="color:#7A84A8;">' . ($en ? 'Unsubscribe' : 'Dezabonează-te') . '</a>' : '');
            app(TenantMailService::class)->send($tenant, function ($m) use ($sub, $html, $name, $en, $unsub) {
                $m->to($sub->email)->from(config('mail.from.address'), $name)
                    ->subject($en ? 'You’re on the ' . $name . ' list' : 'Te-ai abonat la noutățile ' . $name)
                    ->html($html);
                if ($unsub) {
                    $m->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsub . '>');
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Tenant newsletter welcome email failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    private function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 2) . str_repeat('•', max(1, mb_strlen($u) - 2)) . '@' . $d;
    }
}
