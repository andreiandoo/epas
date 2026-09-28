<?php

namespace App\Support;

/**
 * Biletele „Test POS" (Event::ensureTestTicketType()).
 *
 * Sunt bilete de proba, cu valoare simbolica, cu care organizatorul invata
 * aplicatia mobila (vanzare, printare, scanare). NU exista in bani: nu intra
 * in decont, factura POS, comision, sold, declaratii, dashboard sau rapoarte.
 *
 * Doua plase de siguranta, folosite impreuna:
 *
 *   1. SURSA COMENZII — o comanda cu bilete numai de test are source
 *      'pos_test' (decisa pe server la vanzare). self::ORDER_SOURCES e lista
 *      pentru interogarile la nivel de comanda (SUM(orders.total) etc.).
 *
 *   2. TIPUL BILETULUI — self::excludeTickets() scoate biletele de test din
 *      orice interogare pe `tickets`, indiferent de sursa comenzii. Acopera
 *      vanzarile vechi scrise gresit ca 'pos_app' / 'venue_owner_pos'.
 *
 * Detectia e aceeasi ca TicketType::isTestPos(): meta.is_test = true SAU
 * numele „Test POS" (randuri vechi, create fara flag).
 */
final class TestPos
{
    /** Surse de comenzi de test, excluse din orice cifra de vanzari. */
    public const ORDER_SOURCES = ['pos_test', 'test_order'];

    /**
     * Conditie SQL adevarata pentru un tip de bilet Test POS.
     * $alias = numele/aliasul tabelului ticket_types din interogare ('' = fara prefix).
     */
    public static function typeSql(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias.'.' : '';

        return "(LOWER(COALESCE({$p}meta->>'is_test', '')) IN ('true', '1')"
            ." OR LOWER(TRIM(COALESCE(CAST({$p}name AS TEXT), ''))) = 'test pos')";
    }

    /** Conditie SQL adevarata pentru un tip de bilet care NU e Test POS. */
    public static function notTypeSql(string $alias = ''): string
    {
        return 'NOT '.self::typeSql($alias);
    }

    /**
     * Scoate biletele de test dintr-o interogare pe tickets.
     * Biletele fara tip (ticket_type_id NULL) raman — nu sunt de test.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function excludeTickets($query, string $column = 'ticket_type_id')
    {
        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhereNotIn($column, function ($sub) {
                    $sub->select('ticket_types.id')->from('ticket_types')->whereRaw(self::typeSql('ticket_types'));
                });
        });
    }

    /**
     * Scoate comenzile de test dintr-o interogare pe orders.
     * Comenzile fara sursa (NULL) raman.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function excludeOrders($query, string $column = 'source')
    {
        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)->orWhereNotIn($column, self::ORDER_SOURCES);
        });
    }
}
