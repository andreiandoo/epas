<?php

namespace App\Http\Controllers\Api\TixelloApp\Concerns;

use App\Models\MarketplaceOrganizer;
use App\Models\TixelloAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rezolvă organizatorul legat de contul Tixello curent.
 *
 * PARTENERUL RAMANE AUTORITATEA: statusul se verifica la FIECARE cerere, nu
 * doar la login. Daca partenerul dezactiveaza organizatorul, aplicatia se
 * inchide singura.
 *
 * Scoping-ul TREBUIE sa vina de aici, din legatura — niciodata din ce trimite
 * aplicatia. Cine poate scana bilete trebuie sa poata scana doar ale lui.
 */
trait ResolvesLinkedOrganizer
{
    protected function organizerFor(Request $request): ?MarketplaceOrganizer
    {
        /** @var TixelloAccount|null $account */
        $account = $request->attributes->get('tixello_account');
        if (! $account) {
            return null;
        }

        $link = $account->activeLinks()->ofKind('marketplace_organizer')->first();
        if (! $link) {
            return null;
        }

        $org = MarketplaceOrganizer::find($link->linked_id);

        if (! $org || ($org->status ?? 'active') !== 'active') {
            return null;
        }

        return $org;
    }

    protected function noOrganizer(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Niciun cont de organizator conectat.',
        ], 403);
    }
}
