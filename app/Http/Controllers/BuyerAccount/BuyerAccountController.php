<?php

namespace App\Http\Controllers\BuyerAccount;

use App\Http\Controllers\Controller;
use App\Models\TixelloBuyerAccount;
use App\Services\BuyerAccounts\TixelloBuyerAccountService;
use Illuminate\Http\Request;

/**
 * Contul de cumpărător Tixello (/contul-meu): intrare cu link pe email, fără parolă,
 * biletele de la toți organizatorii Tixello, acordul pentru informări și ștergerea contului.
 */
class BuyerAccountController extends Controller
{
    private const SESSION = 'tixello_buyer_account_id';

    public function __construct(private TixelloBuyerAccountService $accounts) {}

    public function show(Request $request)
    {
        $account = $this->current($request);
        if (! $account) {
            return view('buyer-account.login', ['sent' => session('buyer_link_sent')]);
        }

        return view('buyer-account.dashboard', [
            'account' => $account,
            'orders' => $this->accounts->orders($account),
        ]);
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:190'], [
            'email.required' => 'Scrie adresa de email.',
            'email.email' => 'Adresa de email nu pare corectă.',
        ]);
        $this->accounts->requestLink($data['email']);

        // Același mesaj indiferent dacă adresa are cont sau nu
        return redirect()->route('buyer-account.show')->with('buyer_link_sent', strtolower($data['email']));
    }

    /** Linkul din email (semnat, 30 de minute). */
    public function login(Request $request, TixelloBuyerAccount $account)
    {
        if (! $account->account_enabled) {
            return redirect()->route('buyer-account.show');
        }
        $account->forceFill(['email_verified_at' => $account->email_verified_at ?? now(), 'last_login_at' => now()])->save();
        $request->session()->regenerate();
        $request->session()->put(self::SESSION, $account->id);

        return redirect()->route('buyer-account.show');
    }

    /** Pagina de creare a contului, din linkul semnat trimis pe email. */
    public function createForm(Request $request)
    {
        return view('buyer-account.create', [
            'email' => (string) $request->query('email'),
            'action' => $request->fullUrl(),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate(['accept' => 'accepted'], ['accept.accepted' => 'Bifează acordul ca să creezi contul.']);
        $account = $this->accounts->createFromPortal((string) $request->query('email'), $request->ip(), $request->userAgent());
        $request->session()->regenerate();
        $request->session()->put(self::SESSION, $account->id);

        return redirect()->route('buyer-account.show');
    }

    public function marketing(Request $request)
    {
        $account = $this->current($request);
        abort_unless($account, 403);
        $this->accounts->setMarketing($account, $request->boolean('on'), 'portal', $request->ip(), $request->userAgent());

        return redirect()->route('buyer-account.show')->with('status', $request->boolean('on')
            ? 'Gata, primești recomandările Tixello.'
            : 'Gata, nu mai primești recomandări de la Tixello.');
    }

    public function destroy(Request $request)
    {
        $account = $this->current($request);
        abort_unless($account, 403);
        $request->validate(['confirm' => 'accepted'], ['confirm.accepted' => 'Bifează confirmarea ca să ștergi contul.']);
        $this->accounts->delete($account, $request->ip(), $request->userAgent());
        $request->session()->forget(self::SESSION);

        return redirect()->route('buyer-account.show')->with('status', 'Contul tău Tixello a fost șters. Biletele rămân valabile și le ai în emailurile de confirmare.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(self::SESSION);

        return redirect()->route('buyer-account.show');
    }

    /** Dezabonarea dintr-un click (link semnat, fără autentificare). */
    public function unsubscribe(Request $request, TixelloBuyerAccount $account)
    {
        $this->accounts->setMarketing($account, false, 'unsubscribe', $request->ip(), $request->userAgent());

        return view('buyer-account.unsubscribed', ['account' => $account]);
    }

    private function current(Request $request): ?TixelloBuyerAccount
    {
        $id = $request->session()->get(self::SESSION);

        return $id ? TixelloBuyerAccount::find($id) : null;
    }
}
