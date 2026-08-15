package com.tixello.widget

import java.text.DecimalFormat
import java.text.DecimalFormatSymbols
import java.util.Locale
import kotlin.math.abs

/**
 * Formatare romaneasca: mie cu punct, zecimale cu virgula.
 *
 * Widget-ul are latimea unui ecran de telefon impartita la trei, deci cifrele
 * mari se scurteaza („1,25 mil"). Ecranul de configurare le arata intregi.
 */
object Format {

    private val symbols = DecimalFormatSymbols(Locale("ro", "RO")).apply {
        groupingSeparator = '.'
        decimalSeparator = ','
    }

    private val money = DecimalFormat("#,##0.00", symbols)
    /* Milioanele pastreaza doua zecimale (1,25 mil), miile una (123,4 mii):
       la milion, o singura zecimala ar ascunde zeci de mii. */
    private val millions = DecimalFormat("#,##0.##", symbols)
    private val thousands = DecimalFormat("#,##0.#", symbols)
    private val integer = DecimalFormat("#,##0", symbols)

    /** Simbolul monedei, cand exista unul scurt si recunoscut. */
    fun currencySymbol(currency: String): String = when (currency.uppercase()) {
        "EUR" -> "€"
        "USD" -> "$"
        "GBP" -> "£"
        "RON" -> "lei"
        else -> currency.uppercase()
    }

    /** Suma intreaga: `€12.345,67`, `12.345,67 lei`. */
    fun money(value: Double, currency: String): String {
        val symbol = currencySymbol(currency)
        val amount = money.format(value)

        return if (symbol.length == 1) "$symbol$amount" else "$amount $symbol"
    }

    /** Suma scurtata pentru widget: `€1,25 mil`, `€12,3 mii`, `€345,60`. */
    fun moneyCompact(value: Double, currency: String): String {
        val symbol = currencySymbol(currency)
        val amount = compactNumber(value)

        return if (symbol.length == 1) "$symbol$amount" else "$amount $symbol"
    }

    /** Numere intregi cu separator de mii, scurtate peste 100.000. */
    fun count(value: Long): String =
        if (abs(value) >= 100_000) compactNumber(value.toDouble()) else integer.format(value)

    /** `+12` / `0` — pentru randul „azi". */
    fun delta(value: Long): String = if (value > 0) "+${count(value)}" else count(value)

    fun deltaMoney(value: Double, currency: String): String {
        val formatted = moneyCompact(value, currency)

        return if (value > 0) "+$formatted" else formatted
    }

    private fun compactNumber(value: Double): String {
        val absolute = abs(value)

        return when {
            absolute >= 1_000_000 -> millions.format(value / 1_000_000) + " mil"
            absolute >= 100_000 -> thousands.format(value / 1_000) + " mii"
            else -> money.format(value)
        }
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
