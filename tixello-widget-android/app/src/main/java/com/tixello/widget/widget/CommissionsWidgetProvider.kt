package com.tixello.widget.widget

import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.Context
import android.content.Intent
import com.tixello.widget.PollScheduler

/** Widget-ul cu ultimele 5 comisioane castigate si evenimentul lor. */
class CommissionsWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, manager: AppWidgetManager, appWidgetIds: IntArray) {
        appWidgetIds.forEach { manager.updateAppWidget(it, WidgetRenderer.buildCommissions(context)) }

        PollScheduler.requestOneOffSync(context)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)

        if (intent.action == WidgetRenderer.ACTION_REFRESH) {
            PollScheduler.requestOneOffSync(context)
        }
    }

    override fun onEnabled(context: Context) {
        PollScheduler.onWidgetPlaced(context)
    }
}
