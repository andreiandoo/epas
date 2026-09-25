<?php

namespace App\Support;

use App\Services\Marketplace\SalesBreakdownService;

/**
 * Sursele unei comenzi.
 *
 * ATENTIE: „POS" are doua liste, cu roluri diferite (azi au acelasi
 * continut, dar raman separate):
 *
 *   1. DECONTARE / PAYOUT  → SalesBreakdownService::POS_SOURCES = ['pos_app','pos','venue_owner_pos']
 *      Din 2026-09-25 contine si `venue_owner_pos`: locatia vinde la intrare
 *      in numele organizatorului, deci se deconteaza exact ca POS-ul lui
 *      (comisionul pe factura POS). Orice cifra care intra intr-o
 *      compensare trebuie sa foloseasca ASTA.
 *
 *   2. RAPORTARE / afisaj  → self::POS_REPORTING = ['pos_app','venue_owner_pos','pos']
 *      Folosita de BillingBreakdown si SalesBreakdown, unde intereseaza
 *      „cat s-a vandut fizic", nu cine cui datoreaza.
 *
 * Regula practica: daca cifra ajunge intr-un decont, foloseste lista
 * serviciului. Daca e doar informativa, poate folosi POS_REPORTING — dar
 * NICIODATA amestecate in acelasi bloc, altfel `cash + card` poate depasi
 * `gross` si raportul se contrazice singur. Exact asta se intamplase in
 * defalcarea de decontare din Dashboard.
 */
final class OrderSource
{
    /** Vanzare fizica, pentru RAPOARTE informative. NU pentru deconturi. */
    public const POS_REPORTING = ['pos_app', 'venue_owner_pos', 'pos'];

    /** Comenzi care NU trebuie sa intre in cifrele de vanzari. */
    public const EXCLUDED = ['test_order', 'pos_test', 'external_import', 'legacy_import'];

    /**
     * Sursa scrisa de vanzarea la usa din aplicatia Tixello.
     * E in AMBELE liste, deci vanzarile aplicatiei sunt numarate corect si in
     * rapoarte, si in deconturi.
     */
    public const TIXELLO_APP_POS = 'pos_app';

    /**
     * Echivalentul de pe partea de TENANT (`OperatorController::sale`).
     * Nu apare in rapoartele de marketplace, deci nu e in niciuna din liste.
     */
    public const TENANT_POS = 'pos_tenant';

    /** Lista corecta pentru orice cifra care intra intr-un decont. */
    public static function settlementPos(): array
    {
        return SalesBreakdownService::POS_SOURCES;
    }

    public static function isReportingPos(?string $source): bool
    {
        return $source !== null && in_array($source, self::POS_REPORTING, true);
    }
}
