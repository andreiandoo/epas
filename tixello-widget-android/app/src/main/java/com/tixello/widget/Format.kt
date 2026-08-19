package com.tixello.widget

import java.text.DecimalFormat
import java.text.DecimalFormatSymbols
import java.util.Locale
import kotlin.math.abs

/**
 * Formatare romaneasca: mie cu punct, zecimale cu virgula.
 *
 * User a cerut explicit numere COMPLETE, nu prescurtate — deci nu mai
 * folosim „mil" / „mii" nicaieri in widget. Numerele intregi (bilete,
 * clienti, artisti, venue-uri) apar cu separator de mii; sumele au 2
 * zecimale (sau zero pentru sume rotunde daca vrem sa economisim spatiu).
 */
object Format {

    private val symbols = DecimalFormatSymbols(Locale("ro", "RO")).apply {
        groupingSeparator = '.'
        decimalSeparator = ','
    }

    private val money = DecimalFormat("#,##0.00", symbols)
    private val moneyNoDecimals = DecimalFormat("#,##0", symbols)
    private val integer = DecimalFormat("#,##0", symbols)
    private val percent = DecimalFormat("#,##0.#", symbols)

    /** Simbolul monedei, cand exista unul scurt si recunoscut. */
    fun currencySymbol(currency: String): String = when (currency.uppercase()) {
        "EUR" -> "€"
        "USD" -> "$"
        "GBP" -> "£"
        "RON" -> "lei"
        else -> currency.uppercase()
    }

    /** Suma completa cu 2 zecimale: `€12.345,67`, `12.345,67 lei`. */
    fun money(value: Double, currency: String): String {
        val symbol = currencySymbol(currency)
        val amount = money.format(value)

        return if (symbol.length == 1) "$symbol$amount" else "$amount $symbol"
    }

    /** Suma completa fara zecimale — pentru cifre foarte mari cand spatiu e strans. */
    fun moneyRounded(value: Double, currency: String): String {
        val symbol = currencySymbol(currency)
        val amount = moneyNoDecimals.format(value)

        return if (symbol.length == 1) "$symbol$amount" else "$amount $symbol"
    }

    /**
     * Alias de compatibilitate pentru codul vechi care apela moneyCompact.
     * Redirectat la money() ca sa nu apara prescurtari.
     */
    fun moneyCompact(value: Double, currency: String): String = money(value, currency)

    /** Numere intregi cu separator de mii, mereu complete. */
    fun count(value: Long): String = integer.format(value)

    /** `+12` / `0` — pentru randul „azi". */
    fun delta(value: Long): String = if (value > 0) "+${count(value)}" else count(value)

    fun deltaMoney(value: Double, currency: String): String {
        val formatted = money(value, currency)

        return if (value > 0) "+$formatted" else formatted
    }

    /** `+12,3%` / `-4,5%` / `—` pentru delta lunar. */
    fun deltaPercent(current: Double, previous: Double): String {
        if (previous <= 0.0) return if (current > 0.0) "nou" else "—"
        val pct = (current - previous) / previous * 100.0
        val sign = if (pct > 0) "+" else ""
        return "$sign${percent.format(pct)}%"
    }

    /** „acum", „acum 4 min", „acum 2 h", „ieri". */
    fun relativeTime(millis: Long, now: Long = System.currentTimeMillis()): String {
        if (millis <= 0) return "niciodata"

        val seconds = (now - millis) / 1000

        return when {
            seconds < 0 -> "acum"
            seconds < 60 -> "acum"
            seconds < 3600 -> "acum ${seconds / 60} min"
            seconds < 86_400 -> "acum ${seconds / 3600} h"
            seconds < 172_800 -> "ieri"
            else -> "acum ${seconds / 86_400} zile"
        }
    }

    /**
     * Ora dintr-un timestamp ISO-8601 („2026-08-15T14:05:09+00:00"), in fusul
     * telefonului. Fara parser strict: daca serverul trimite altceva decat ne
     * asteptam, mai bine nu afisam ora decat sa cada widget-ul.
     */
    fun isoToLocalTime(iso: String?): String? {
        if (iso.isNullOrBlank()) return null

        return try {
            val instant = java.time.OffsetDateTime.parse(iso).toInstant()
            java.time.format.DateTimeFormatter
                .ofPattern("HH:mm", Locale("ro", "RO"))
                .withZone(java.time.ZoneId.systemDefault())
                .format(instant)
        } catch (e: Exception) {
            null
        }
    }
}
