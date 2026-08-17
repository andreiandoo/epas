package com.tixello.widget.widget

import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.Context
import android.content.Intent
import com.tixello.widget.PollScheduler

/**
 * Widget-ul cu cifre: vanzari, bilete, clienti + veniturile Tixello, fiecare
 * cu valoarea totala si cea de azi.
 */
class StatsWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, manager: AppWidgetManager, appWidgetIds: IntArray) {
        appWidgetIds.forEach { manager.updateAppWidget(it, WidgetRenderer.buildStats(context)) }

        /* Sistemul cheama onUpdate rar (vezi updatePeriodMillis). Cerem si o
           sincronizare, ca widget-ul proaspat pus pe ecran sa nu stea gol. */
        PollScheduler.requestOneOffSync(context)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)

        if (intent.action == WidgetRenderer.ACTION_REFRESH) {
            PollScheduler.requestOneOffSync(context)
        }
    }

    override fun onEnabled(context: Context) {
        /* Primul widget pus pe ecran porneste si urmarirea, daca aplicatia e
           deja configurata — altfel ar arata cifre inghetate. */
        PollScheduler.onWidgetPlaced(context)
    }
}
