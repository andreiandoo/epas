package com.tixello.widget

import org.json.JSONObject

/**
 * Cifrele primite de la `/api/tixello-widget/summary`, in forma pe care o
 * deseneaza widget-ul.
 *
 * Parsarea e tolerante prin constructie: orice camp lipsa cade pe 0 / null.
 * Un server mai vechi decat aplicatia nu trebuie sa lase widget-ul gol.
 */
data class Commission(
    val id: Long,
    val event: String,
    val source: String?,
    val amount: Double,
    val amountCurrency: String,
    val amountConverted: Double?,
    val currency: String,
    val at: String?
) {
    /** Suma de afisat, in moneda de raportare cand exista curs. */
    val displayAmount: Double get() = amountConverted ?: amount
    val displayCurrency: String get() = if (amountConverted != null) currency else amountCurrency
}

data class Snapshot(
    val generatedAt: String?,
    val currency: String,
    val secondaryCurrency: String?,
    val pollIntervalSeconds: Int,
    val salesTotal: Double,
    val salesTotalSecondary: Double?,
    val salesTotalOrders: Long,
    val salesToday: Double,
    val salesTodaySecondary: Double?,
    val salesTodayOrders: Long,
    val ticketsTotal: Long,
    val ticketsToday: Long,
    val customersTotal: Long,
    val customersToday: Long,
    val revenueTotal: Double,
    val revenueToday: Double,
    val commissions: List<Commission>,
    val newCommissions: List<Commission>,
    val lastCommissionId: Long
) {
    companion object {

        /** @param json obiectul `data` din raspunsul serverului. */
        fun parse(json: JSONObject): Snapshot {
            val stats = json.optJSONObject("stats") ?: JSONObject()
            val sales = stats.optJSONObject("sales") ?: JSONObject()
            val tickets = stats.optJSONObject("tickets") ?: JSONObject()
            val customers = stats.optJSONObject("customers") ?: JSONObject()
            val revenue = stats.optJSONObject("revenue") ?: JSONObject()

            return Snapshot(
                generatedAt = json.optStringOrNull("generated_at"),
                currency = json.optString("currency", "EUR"),
                secondaryCurrency = json.optStringOrNull("secondary_currency"),
                pollIntervalSeconds = json.optInt("poll_interval_seconds", Prefs.DEFAULT_POLL_SECONDS),
                salesTotal = sales.optDouble("total", 0.0).orZero(),
                salesTotalSecondary = sales.optDoubleOrNull("total_secondary"),
                salesTotalOrders = sales.optLong("total_orders", 0L),
                salesToday = sales.optDouble("today", 0.0).orZero(),
                salesTodaySecondary = sales.optDoubleOrNull("today_secondary"),
                salesTodayOrders = sales.optLong("today_orders", 0L),
                ticketsTotal = tickets.optLong("total", 0L),
                ticketsToday = tickets.optLong("today", 0L),
                customersTotal = customers.optLong("total", 0L),
                customersToday = customers.optLong("today", 0L),
                revenueTotal = revenue.optDouble("total", 0.0).orZero(),
                revenueToday = revenue.optDouble("today", 0.0).orZero(),
                commissions = json.optJSONArray("commissions").toCommissions(),
                newCommissions = json.optJSONArray("new_commissions").toCommissions(),
                lastCommissionId = json.optJSONObject("cursor")?.optLong("last_commission_id", 0L) ?: 0L
            )
        }

        fun parse(raw: String): Snapshot? = try {
            parse(JSONObject(raw))
        } catch (e: Exception) {
            null
        }

        private fun org.json.JSONArray?.toCommissions(): List<Commission> {
            if (this == null) return emptyList()
            val out = ArrayList<Commission>(length())
            for (i in 0 until length()) {
                val o = optJSONObject(i) ?: continue
                out.add(
                    Commission(
                        id = o.optLong("id", 0L),
                        event = o.optString("event", "Eveniment necunoscut"),
                        source = o.optStringOrNull("source"),
                        amount = o.optDouble("amount", 0.0).orZero(),
                        amountCurrency = o.optString("amount_currency", "EUR"),
                        amountConverted = o.optDoubleOrNull("amount_converted"),
                        currency = o.optString("currency", "EUR"),
                        at = o.optStringOrNull("at")
                    )
                )
            }
            return out
        }

        private fun JSONObject.optStringOrNull(key: String): String? =
            if (isNull(key)) null else optString(key, "").ifEmpty { null }

        private fun JSONObject.optDoubleOrNull(key: String): Double? =
            if (isNull(key)) null else optDouble(key).let { if (it.isNaN()) null else it }

        private fun Double.orZero(): Double = if (isNaN()) 0.0 else this
    }
}
