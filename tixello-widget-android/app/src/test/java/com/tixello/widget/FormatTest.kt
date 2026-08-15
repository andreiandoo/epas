package com.tixello.widget

import org.junit.Assert.assertEquals
import org.junit.Test

/** Formatarea cifrelor din widget — separatori romanesti si scurtari. */
class FormatTest {

    @Test
    fun `sumele mici se scriu intregi`() {
        assertEquals("€1.234,50", Format.money(1234.5, "EUR"))
        assertEquals("1.234,50 lei", Format.money(1234.5, "RON"))
    }

    @Test
    fun `sumele mari se scurteaza in widget`() {
        assertEquals("€1,25 mil", Format.moneyCompact(1_250_000.0, "EUR"))
        assertEquals("€123,4 mii", Format.moneyCompact(123_400.0, "EUR"))
        assertEquals("€999,99", Format.moneyCompact(999.99, "EUR"))
    }

    @Test
    fun `numerele intregi primesc separator de mii`() {
        assertEquals("9.312", Format.count(9312))
        assertEquals("123,5 mii", Format.count(123_456))
    }

    @Test
    fun `randul de azi are semn doar cand e pozitiv`() {
        assertEquals("+41", Format.delta(41))
        assertEquals("0", Format.delta(0))
    }

    @Test
    fun `moneda necunoscuta ramane cod`() {
        assertEquals("10,00 HUF", Format.money(10.0, "HUF"))
    }

    @Test
    fun `vechimea sincronizarii e in cuvinte`() {
        val now = 1_000_000_000L

        assertEquals("niciodata", Format.relativeTime(0, now))
        assertEquals("acum", Format.relativeTime(now - 5_000, now))
        assertEquals("acum 4 min", Format.relativeTime(now - 4 * 60_000, now))
        assertEquals("acum 2 h", Format.relativeTime(now - 2 * 3_600_000, now))
    }
}
