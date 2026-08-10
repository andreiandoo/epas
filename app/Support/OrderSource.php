<?php

namespace App\Support;

/**
 * Sursele unei comenzi — o singură definiție a ce înseamnă „POS".
 *
 * DE CE EXISTA
 * Clasificarea POS era scrisa de mana in mai multe locuri si NU coincidea:
 *   BillingBreakdown / SalesBreakdown : ['pos_app', 'venue_owner_pos', 'pos']
 *   Dashboard (defalcarea zilnica)    : doar 'pos'
 * Consecinta: o comanda cu `source = 'pos_app'` aparea drept POS in unele
 * rapoarte si drept ONLINE in altele, iar comisionul iesea diferit de la o
 * pagina la alta pentru acelasi eveniment.
 *
 * Aplicatia Tixello scrie `pos_app` la vanzarea de la usa, deci fara
 * unificarea asta ar fi intrat direct in aceeasi capcana.
 */
final class OrderSource
{
    /** Vanzare fizica, la usa sau la casa — indiferent de aplicatia folosita. */
    public const POS = ['pos_app', 'venue_owner_pos', 'pos'];

    /** Comenzi care NU trebuie sa intre in cifrele de vanzari. */
    public const EXCLUDED = ['test_order', 'pos_test', 'external_import', 'legacy_import'];

    /** Sursa scrisa de vanzarea la usa din aplicatia Tixello. */
    public const TIXELLO_APP_POS = 'pos_app';

    /**
     * Echivalentul de pe partea de TENANT (`OperatorController::sale`).
     * NU e inclus deliberat in `POS`: lista aia deserveste rapoartele de
     * marketplace, unde sursa asta nu apare, iar adaugarea ei ar schimba
     * cifre fara motiv. E aici doar ca sa fie documentata langa surori.
     */
    public const TENANT_POS = 'pos_tenant';

    public static function isPos(?string $source): bool
    {
        return $source !== null && in_array($source, self::POS, true);
    }
}
