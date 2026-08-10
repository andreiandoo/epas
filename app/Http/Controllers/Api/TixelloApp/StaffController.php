<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Api\MarketplaceClient\Organizer\TeamController;
use App\Http\Controllers\Api\TixelloApp\Concerns\ResolvesLinkedOrganizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personalul de la poartă, din aplicația Tixello.
 *
 * CERINTA a fost „oglinda la ce exista deja pe ambilet.ro". Asta inseamna
 * ACELASI tabel (`marketplace_organizer_team_members`) si ACELEASI reguli —
 * nu o lista paralela.
 *
 * DE CE DELEGARE, SI NU O A DOUA IMPLEMENTARE
 * Regulile de echipa nu-s banale: limita de membri din setarile
 * marketplace-ului, permisiuni derivate din rol, lista de evenimente permise,
 * blocarea propriei adrese, si — cel mai usor de ratat — SINCRONIZAREA
 * PAROLEI intre organizatorii aceluiasi marketplace, ca un om de la poarta sa
 * poata folosi un singur login peste tot.
 * Rescrise aici, ar diverge de la prima modificare. Asa ca aplicam exact
 * acelasi cod: rezolvam organizatorul din legatura Tixello, il punem ca
 * utilizator al cererii si dam mai departe controllerului existent.
 *
 * Consecinta buna: orice schimbare viitoare a regulilor de echipa se aplica
 * automat in ambele aplicatii, fara sa trebuiasca sa-si aminteasca cineva.
 */
class StaffController extends Controller
{
    use ResolvesLinkedOrganizer;

    /**
     * Ruleaza o metoda a controllerului de echipa ca si cum cererea ar veni
     * din aplicatia partenerului.
     */
    private function asOrganizer(Request $request, string $method): JsonResponse
    {
        $org = $this->organizerFor($request);
        if (! $org) {
            return $this->noOrganizer();
        }

        // TeamController isi ia organizatorul din `$request->user()`; restul
        // (marketplace, setari, limite) deriva din el.
        $request->setUserResolver(fn () => $org);

        return app(TeamController::class)->{$method}($request);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'index');
    }

    /** Creeaza un membru de echipa, legat automat de organizatorul conectat. */
    public function invite(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'invite');
    }

    public function update(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'update');
    }

    /**
     * Dezactivarea trebuie sa fie SIMETRICA: un om scos din echipa aici
     * dispare si din aplicatia partenerului, fiindca e acelasi rand in tabel.
     */
    public function remove(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'remove');
    }

    public function activate(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'activate');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->asOrganizer($request, 'resetPassword');
    }
}
