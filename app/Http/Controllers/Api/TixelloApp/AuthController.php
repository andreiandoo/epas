<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Controller;
use App\Models\TixelloAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Înregistrare și autentificare în aplicația Tixello.
 *
 * DOUA REGULI care nu se negociaza, ambele din discutia de arhitectura:
 *
 * 1. NU verificam niciodata daca emailul exista la vreun partener si nu
 *    spunem asta nimanui. Ar fi enumerare de conturi (oricine tasteaza adrese
 *    afla cine are cont unde) si ar dezvalui relatia dintre platforme.
 *    Legaturile se fac doar prin acordul explicit al omului — vezi LinkController.
 *
 * 2. Emailul se verifica prin cod. E cheia care leaga o comanda facuta in
 *    aplicatie de contul de la partener; fara verificare, cineva s-ar putea
 *    inregistra cu adresa altcuiva.
 */
class AuthController extends Controller
{
    /** Raspunsul public al unui cont — fara nimic din lumile partenerilor. */
    private function accountPayload(TixelloAccount $a, ?string $token = null): array
    {
        return array_filter([
            'token' => $token,
            'account' => [
                'id' => $a->id,
                'email' => $a->email,
                'name' => $a->name,
                'phone' => $a->phone,
                'verified' => $a->isVerified(),
                'is_organizer' => $a->is_organizer,
                'locale' => $a->locale,
            ],
        ], fn ($v) => $v !== null);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:100',
            'name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'device_id' => 'nullable|string|max:100',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $existing = TixelloAccount::where('email', $email)->first();

        if ($existing) {
            /**
             * Raspuns identic cu cel de succes ca forma, dar fara token: nu
             * confirmam ca adresa exista in Tixello (enumerare), iar cine chiar
             * are cont primeste oricum codul pe email si intra.
             */
            $code = $existing->issueVerificationCode();
            $this->sendCode($existing, $code);

            return response()->json([
                'success' => true,
                'data' => ['verification_required' => true, 'email' => $email],
            ]);
        }

        $account = TixelloAccount::create([
            'email' => $email,
            'password' => Hash::make($data['password']),
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
        ]);

        $code = $account->issueVerificationCode();
        $this->sendCode($account, $code);

        return response()->json([
            'success' => true,
            'data' => ['verification_required' => true, 'email' => $email],
        ], 201);
    }

    /** Confirma codul si intoarce tokenul de sesiune. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|max:12',
            'device_id' => 'nullable|string|max:100',
        ]);

        $account = TixelloAccount::where('email', mb_strtolower(trim($data['email'])))->first();

        if (! $account || ! $account->confirmVerificationCode($data['code'])) {
            return response()->json(['success' => false, 'error' => 'Cod invalid sau expirat.'], 422);
        }

        $account->forceFill(['last_login_at' => now()])->save();

        /* Invitatiile care asteptau adresa asta devin CERERI de prietenie —
           nu prietenii. Omul tocmai s-a inregistrat, n-a acceptat inca nimic;
           le vede in ecranul de prieteni si decide el.
           Se face aici, nu la `register`: pana la verificarea emailului nu stim
           ca adresa chiar ii apartine, iar o cerere legata de o adresa
           nedovedita ar fi exact scaparea pe care verificarea o inchide. */
        try {
            app(\App\Services\Friends\FriendshipService::class)->convertPendingInvites($account);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Conversia invitatiilor de prietenie a esuat', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $token = $account->issueToken($data['device_id'] ?? null);

        return response()->json(['success' => true, 'data' => $this->accountPayload($account, $token)]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:100',
        ]);

        $account = TixelloAccount::where('email', mb_strtolower(trim($data['email'])))->first();

        // acelasi mesaj pentru "nu exista" si "parola gresita" — altfel se pot
        // descoperi adresele inregistrate incercand parole
        if (! $account || ! $account->password || ! Hash::check($data['password'], $account->password)) {
            return response()->json(['success' => false, 'error' => 'Date de autentificare incorecte.'], 422);
        }

        if ($account->status !== 'active') {
            return response()->json(['success' => false, 'error' => 'Cont blocat.'], 403);
        }

        if (! $account->isVerified()) {
            $code = $account->issueVerificationCode();
            $this->sendCode($account, $code);

            return response()->json([
                'success' => true,
                'data' => ['verification_required' => true, 'email' => $account->email],
            ]);
        }

        $account->forceFill(['last_login_at' => now()])->save();
        $token = $account->issueToken($data['device_id'] ?? null);

        return response()->json(['success' => true, 'data' => $this->accountPayload($account, $token)]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var TixelloAccount $account */
        $account = $request->attributes->get('tixello_account');

        return response()->json([
            'success' => true,
            'data' => $this->accountPayload($account) + [
                'links' => $account->activeLinks()->get(['id', 'kind', 'marketplace_client_id', 'tenant_id'])->toArray(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var TixelloAccount $account */
        $account = $request->attributes->get('tixello_account');
        $plain = $request->bearerToken();

        if ($plain) {
            $account->tokens()->where('token', hash('sha256', $plain))->delete();
        }

        return response()->json(['success' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $account = TixelloAccount::where('email', mb_strtolower(trim($data['email'])))->first();

        if ($account && ! $account->isVerified()) {
            $this->sendCode($account, $account->issueVerificationCode());
        }

        // raspuns identic indiferent daca adresa exista
        return response()->json(['success' => true]);
    }

    /**
     * Trimiterea codului. Best-effort: daca mailul cade, contul ramane creat
     * si omul poate cere retrimiterea — nu-i pierdem inregistrarea.
     */
    private function sendCode(TixelloAccount $account, string $code): void
    {
        try {
            Mail::raw(
                "Codul tău de verificare Tixello este: {$code}\n\nExpiră în 15 minute.",
                fn ($m) => $m->to($account->email)->subject('Cod de verificare Tixello')
            );
        } catch (\Throwable $e) {
            Log::warning('Tixello app: trimiterea codului a esuat', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
