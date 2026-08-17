package com.tixello.widget

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Repornirea urmaririi dupa restart-ul telefonului sau dupa actualizarea
 * aplicatiei. Fara receiver-ul asta, widget-ul ramane inghetat pe cifrele de
 * dinainte de repornire pana deschizi aplicatia de mana.
 */
class BootReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_BOOT_COMPLETED,
            Intent.ACTION_MY_PACKAGE_REPLACED -> PollScheduler.onDeviceReady(context)
        }
    }
}
