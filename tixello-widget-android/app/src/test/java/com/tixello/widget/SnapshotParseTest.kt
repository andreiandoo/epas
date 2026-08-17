package com.tixello.widget

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Parsarea payload-ului de la `/api/tixello-widget/summary`.
 *
 * Testele astea sunt contractul dintre server si telefon: daca cineva
 * redenumeste un camp in TixelloWidgetStatsService, aici pica.
 */
class SnapshotParseTest {

    private val full = """
        {
          "generated_at": "2026-08-15T10:00:00+00:00",
          "timezone": "Europe/Bucharest",
          "currency": "EUR",
          "secondary_currency": "RON",
          "poll_interval_seconds": 45,
          "stats": {
            "sales": {
              "total": 125000.5, "total_secondary": 622501.5, "total_orders": 4210,
              "today": 890.25, "today_secondary": 4432.1, "today_orders": 17
            },
            "tickets": { "total": 9312, "today": 41 },
            "customers": { "total": 5120, "today": 8, "tenant": 3000, "marketplace": 2120 },
            "revenue": { "total": 4820.75, "total_secondary": 24000.1, "today": 33.4, "today_secondary": 166.2 }
          },
          "commissions": [
            {
              "id": 998, "order_number": "TX-998", "event": "Concert Rock",
              "source": "Ambilet", "amount": 12.5, "amount_currency": "RON",
              "amount_converted": 2.51, "currency": "EUR",
              "at": "2026-08-15T09:59:00+00:00"
            },
            {
              "id": 997, "order_number": "TX-997", "event": "Teatru",
              "source": null, "amount": 4.0, "amount_currency": "EUR",
              "amount_converted": null, "currency": "EUR",
              "at": "2026-08-15T09:30:00+00:00"
            }
          ],
          "new_commissions": [
            {
              "id": 998, "order_number": "TX-998", "event": "Concert Rock",
              "source": "Ambilet", "amount": 12.5, "amount_currency": "RON",
              "amount_converted": 2.51, "currency": "EUR",
              "at": "2026-08-15T09:59:00+00:00"
            }
          ],
          "cursor": { "last_commission_id": 998 }
        }
    """.trimIndent()

    @Test
    fun `citeste toate cifrele`() {
        val snapshot = Snapshot.parse(JSONObject(full))

        assertEquals("EUR", snapshot.currency)
        assertEquals("RON", snapshot.secondaryCurrency)
        assertEquals(45, snapshot.pollIntervalSeconds)
        assertEquals(125000.5, snapshot.salesTotal, 0.001)
        assertEquals(890.25, snapshot.salesToday, 0.001)
        assertEquals(4210L, snapshot.salesTotalOrders)
        assertEquals(17L, snapshot.salesTodayOrders)
        assertEquals(9312L, snapshot.ticketsTotal)
        assertEquals(41L, snapshot.ticketsToday)
        assertEquals(5120L, snapshot.customersTotal)
        assertEquals(8L, snapshot.customersToday)
        assertEquals(4820.75, snapshot.revenueTotal, 0.001)
        assertEquals(33.4, snapshot.revenueToday, 0.001)
        assertEquals(998L, snapshot.lastCommissionId)
    }

    @Test
    fun `comisioanele pastreaza evenimentul si moneda`() {
        val snapshot = Snapshot.parse(JSONObject(full))

        assertEquals(2, snapshot.commissions.size)
        assertEquals(1, snapshot.newCommissions.size)

        val first = snapshot.commissions.first()
        assertEquals("Concert Rock", first.event)
        assertEquals("Ambilet", first.source)
        /* Convertit → afisam in moneda de raportare. */
        assertEquals(2.51, first.displayAmount, 0.001)
        assertEquals("EUR", first.displayCurrency)

        val second = snapshot.commissions[1]
        assertNull(second.source)
        /* Fara curs → ramane suma originala, cu moneda ei. */
        assertEquals(4.0, second.displayAmount, 0.001)
        assertEquals("EUR", second.displayCurrency)
    }

    @Test
    fun `un payload sarac nu arunca`() {
        val snapshot = Snapshot.parse(JSONObject("""{"stats":{}}"""))

        assertEquals(0.0, snapshot.salesTotal, 0.001)
        assertEquals(0L, snapshot.ticketsTotal)
        assertTrue(snapshot.commissions.isEmpty())
        assertTrue(snapshot.newCommissions.isEmpty())
        assertEquals("EUR", snapshot.currency)
    }

    @Test
    fun `un JSON stricat intoarce null in loc sa arunce`() {
        assertNull(Snapshot.parse("nu e json"))
    }
}
