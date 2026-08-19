package com.tixello.widget.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.view.View
import android.widget.RemoteViews
import com.tixello.widget.Commission
import com.tixello.widget.Format
import com.tixello.widget.R
import com.tixello.widget.cachedSnapshot
import com.tixello.widget.isConfigured
import com.tixello.widget.lastError
import com.tixello.widget.syncedAt

/**
 * Deseneaza cele doua widget-uri din ultimul snapshot salvat.
 *
 * Widget-ul nu face niciodata retea: citeste ce a lasat ultima sincronizare.
 * Asa ramane instantaneu la redesenare (rotire, schimbare de tema) si nu
 * consuma baterie cand sistemul il reimprospateaza de capul lui.
 */
object WidgetRenderer {

    const val ACTION_REFRESH = "com.tixello.widget.ACTION_REFRESH"

    /** Reimprospateaza ambele tipuri de widget, oriunde ar fi puse. */
    fun refreshAll(context: Context) {
        val manager = AppWidgetManager.getInstance(context)

        manager.getAppWidgetIds(ComponentName(context, StatsWidgetProvider::class.java))
            .forEach { manager.updateAppWidget(it, buildStats(context)) }

        manager.getAppWidgetIds(ComponentName(context, CommissionsWidgetProvider::class.java))
            .forEach { manager.updateAppWidget(it, buildCommissions(context)) }
    }

    fun buildStats(context: Context): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_stats)
        val snapshot = context.cachedSnapshot()

        applyHeader(context, views)

        if (snapshot == null) {
            /* Fara snapshot: umplem locurile cu — ca sa se vada structura,
               nu un widget gol care nedumereste user-ul. */
            listOf(
                R.id.stat_sales_value, R.id.stat_revenue_value,
                R.id.stat_tickets_value, R.id.stat_customers_value,
                R.id.stat_artists_value, R.id.stat_venues_value
            ).forEach { views.setTextViewText(it, "—") }

            listOf(
                R.id.stat_sales_today, R.id.stat_revenue_today,
                R.id.stat_tickets_today, R.id.stat_customers_today,
                R.id.stat_artists_today, R.id.stat_venues_today
            ).forEach { views.setTextViewText(it, "") }

            /* Golim si tabelul monthly - randurile individuale. */
            listOf(
                R.id.monthly_header_current, R.id.monthly_header_previous,
                R.id.monthly_sales_current, R.id.monthly_sales_previous, R.id.monthly_sales_delta,
                R.id.monthly_tickets_current, R.id.monthly_tickets_previous, R.id.monthly_tickets_delta,
                R.id.monthly_customers_current, R.id.monthly_customers_previous, R.id.monthly_customers_delta,
                R.id.monthly_artists_current, R.id.monthly_artists_previous, R.id.monthly_artists_delta,
                R.id.monthly_venues_current, R.id.monthly_venues_previous, R.id.monthly_venues_delta,
                R.id.monthly_revenue_current, R.id.monthly_revenue_previous, R.id.monthly_revenue_delta
            ).forEach { views.setTextViewText(it, "—") }

            return views
        }

        val currency = snapshot.currency

        /* Rand 1: cele 2 cifre WIDE (vanzari + venit Tixello) — complete, nu compact. */
        views.setTextViewText(R.id.stat_sales_value, Format.money(snapshot.salesTotal, currency))
        views.setTextViewText(
            R.id.stat_sales_today,
            context.getString(R.string.widget_today_value, Format.deltaMoney(snapshot.salesToday, currency))
        )

        views.setTextViewText(R.id.stat_revenue_value, Format.money(snapshot.revenueTotal, currency))
        views.setTextViewText(
            R.id.stat_revenue_today,
            context.getString(R.string.widget_today_value, Format.deltaMoney(snapshot.revenueToday, currency))
        )

        /* Rand 2: cele 4 counts (bilete, clienti, artisti, locatii). */
        views.setTextViewText(R.id.stat_tickets_value, Format.count(snapshot.ticketsTotal))
        views.setTextViewText(R.id.stat_tickets_today, Format.delta(snapshot.ticketsToday))

        views.setTextViewText(R.id.stat_customers_value, Format.count(snapshot.customersTotal))
        views.setTextViewText(R.id.stat_customers_today, Format.delta(snapshot.customersToday))

        views.setTextViewText(R.id.stat_artists_value, Format.count(snapshot.artistsTotal))
        views.setTextViewText(R.id.stat_artists_today, Format.delta(snapshot.artistsToday))

        views.setTextViewText(R.id.stat_venues_value, Format.count(snapshot.venuesTotal))
        views.setTextViewText(R.id.stat_venues_today, Format.delta(snapshot.venuesToday))

        /* Rand 3: TABEL comparatie lunara — 6 metrici × 4 coloane
           (label | current | previous | delta%). User a cerut breakdown
           per fiecare cifra, nu doar venit total. */
        val current = snapshot.monthlyCurrent
        val previous = snapshot.monthlyPrevious
        if (current != null && previous != null) {
            views.setTextViewText(R.id.monthly_header_current, current.label)
            views.setTextViewText(R.id.monthly_header_previous, previous.label)

            fillMonthlyRow(
                views,
                curId = R.id.monthly_sales_current,
                prevId = R.id.monthly_sales_previous,
                deltaId = R.id.monthly_sales_delta,
                current = current.sales,
                previous = previous.sales,
                formatValue = { Format.moneyRounded(it, currency) }
            )
            fillMonthlyRow(
                views,
                curId = R.id.monthly_tickets_current,
                prevId = R.id.monthly_tickets_previous,
                deltaId = R.id.monthly_tickets_delta,
                current = current.tickets.toDouble(),
                previous = previous.tickets.toDouble(),
                formatValue = { Format.count(it.toLong()) }
            )
            fillMonthlyRow(
                views,
                curId = R.id.monthly_customers_current,
                prevId = R.id.monthly_customers_previous,
                deltaId = R.id.monthly_customers_delta,
                current = current.customers.toDouble(),
                previous = previous.customers.toDouble(),
                formatValue = { Format.count(it.toLong()) }
            )
            fillMonthlyRow(
                views,
                curId = R.id.monthly_artists_current,
                prevId = R.id.monthly_artists_previous,
                deltaId = R.id.monthly_artists_delta,
                current = current.artists.toDouble(),
                previous = previous.artists.toDouble(),
                formatValue = { Format.count(it.toLong()) }
            )
            fillMonthlyRow(
                views,
                curId = R.id.monthly_venues_current,
                prevId = R.id.monthly_venues_previous,
                deltaId = R.id.monthly_venues_delta,
                current = current.venues.toDouble(),
                previous = previous.venues.toDouble(),
                formatValue = { Format.count(it.toLong()) }
            )
            fillMonthlyRow(
                views,
                curId = R.id.monthly_revenue_current,
                prevId = R.id.monthly_revenue_previous,
                deltaId = R.id.monthly_revenue_delta,
                current = current.revenue,
                previous = previous.revenue,
                formatValue = { Format.moneyRounded(it, currency) }
            )
        } else {
            /* Fara date monthly: golim toate celulele si dam '—' la fiecare. */
            listOf(
                R.id.monthly_header_current, R.id.monthly_header_previous
            ).forEach { views.setTextViewText(it, "—") }

            listOf(
                R.id.monthly_sales_current, R.id.monthly_sales_previous, R.id.monthly_sales_delta,
                R.id.monthly_tickets_current, R.id.monthly_tickets_previous, R.id.monthly_tickets_delta,
                R.id.monthly_customers_current, R.id.monthly_customers_previous, R.id.monthly_customers_delta,
                R.id.monthly_artists_current, R.id.monthly_artists_previous, R.id.monthly_artists_delta,
                R.id.monthly_venues_current, R.id.monthly_venues_previous, R.id.monthly_venues_delta,
                R.id.monthly_revenue_current, R.id.monthly_revenue_previous, R.id.monthly_revenue_delta
            ).forEach { views.setTextViewText(it, "—") }
        }

        return views
    }

    private fun fillMonthlyRow(
        views: RemoteViews,
        curId: Int,
        prevId: Int,
        deltaId: Int,
        current: Double,
        previous: Double,
        formatValue: (Double) -> String
    ) {
        views.setTextViewText(curId, formatValue(current))
        views.setTextViewText(prevId, formatValue(previous))
        views.setTextViewText(deltaId, Format.deltaPercent(current, previous))
    }

    fun buildCommissions(context: Context): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_commissions)
        val snapshot = context.cachedSnapshot()

        applyHeader(context, views)

        val commissions = snapshot?.commissions.orEmpty()

        views.setViewVisibility(R.id.commissions_empty, if (commissions.isEmpty()) View.VISIBLE else View.GONE)
        views.setTextViewText(
            R.id.commissions_empty,
            if (snapshot == null) context.getString(R.string.widget_not_synced)
            else context.getString(R.string.widget_no_commissions)
        )

        ROW_IDS.forEachIndexed { index, ids ->
            val commission = commissions.getOrNull(index)

            if (commission == null) {
                views.setViewVisibility(ids.row, View.GONE)
                return@forEachIndexed
            }

            views.setViewVisibility(ids.row, View.VISIBLE)
            views.setTextViewText(ids.event, commission.event)
            views.setTextViewText(ids.meta, metaLine(commission))
            views.setTextViewText(
                ids.amount,
                Format.money(commission.displayAmount, commission.displayCurrency)
            )
        }

        if (snapshot != null) {
            views.setTextViewText(
                R.id.commissions_footer,
                context.getString(
                    R.string.widget_commissions_footer,
                    Format.money(snapshot.revenueToday, snapshot.currency)
                )
            )
        }

        return views
    }

    /** Ora ultimei sincronizari + starea (eroare / neconfigurat). */
    private fun applyHeader(context: Context, views: RemoteViews) {
        val error = context.lastError
        val status = when {
            !context.isConfigured() -> context.getString(R.string.widget_setup_needed)
            error != null -> context.getString(R.string.widget_error_short)
            else -> Format.relativeTime(context.syncedAt)
        }

        views.setTextViewText(R.id.widget_updated, status)

        /* Tot antetul deschide aplicatia; iconita de refresh cere o
           sincronizare imediata. */
        views.setOnClickPendingIntent(R.id.widget_title, openApp(context))
        views.setOnClickPendingIntent(R.id.widget_refresh, refreshIntent(context))
    }

    private fun metaLine(commission: Commission): String =
        listOfNotNull(Format.isoToLocalTime(commission.at), commission.source)
            .joinToString(" · ")

    private fun openApp(context: Context): PendingIntent {
        val intent = context.packageManager.getLaunchIntentForPackage(context.packageName)
            ?: Intent()

        return PendingIntent.getActivity(
            context,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    /**
     * Butonul de refresh al AMBELOR widget-uri trece prin StatsWidgetProvider:
     * el cere o sincronizare, iar sincronizarea redeseneaza tot ce e pe ecran.
     */
    private fun refreshIntent(context: Context): PendingIntent {
        val intent = Intent(context, StatsWidgetProvider::class.java).setAction(ACTION_REFRESH)

        return PendingIntent.getBroadcast(
            context,
            1,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    /**
     * RemoteViews nu poate adresa view-uri dintr-un `<include>` repetat (ID-uri
     * duplicate), deci cele 5 randuri sunt scrise explicit in layout si tinute
     * aici.
     */
    private data class RowIds(val row: Int, val event: Int, val meta: Int, val amount: Int)

    private val ROW_IDS = listOf(
        RowIds(R.id.c1_row, R.id.c1_event, R.id.c1_meta, R.id.c1_amount),
        RowIds(R.id.c2_row, R.id.c2_event, R.id.c2_meta, R.id.c2_amount),
        RowIds(R.id.c3_row, R.id.c3_event, R.id.c3_meta, R.id.c3_amount),
        RowIds(R.id.c4_row, R.id.c4_event, R.id.c4_meta, R.id.c4_amount),
        RowIds(R.id.c5_row, R.id.c5_event, R.id.c5_meta, R.id.c5_amount)
    )
}
