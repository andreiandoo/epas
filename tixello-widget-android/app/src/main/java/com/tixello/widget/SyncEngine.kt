package com.tixello.widget

import android.content.Context
import android.util.Log
import com.tixello.widget.widget.WidgetRenderer
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

/**
 * Un singur loc unde se face „trage cifrele, salveaza-le, deseneaza widget-ul,
 * alerteaza daca e cazul".
 *
 * Serviciul foreground, worker-ul de rezerva, butonul de refresh din widget si
 * ecranul de configurare cheama toate metoda asta — altfel fiecare ar avea
 * propria versiune a regulii „ce inseamna comision nou".
 *
 * Mutex-ul e esential, nu cosmetic: serviciul si worker-ul pot cadea in
 * acelasi moment, iar doua sincronizari paralele ar citi acelasi cursor si ar
 * suna de doua ori pentru acelasi comision.
 */
object SyncEngine {

    private const val TAG = "TixelloSync"
    private val mutex = Mutex()

    sealed class Outcome {
        data class Ok(val snapshot: Snapshot, val alerted: Int) : Outcome()
        data class Failed(val message: String) : Outcome()
        object NotConfigured : Outcome()
    }

    suspend fun sync(context: Context, allowAlerts: Boolean = true): Outcome = mutex.withLock {
        val app = context.applicationContext

        if (!app.isConfigured()) return@withLock Outcome.NotConfigured

        /* Cursorul de dinainte de cerere. Serverul primeste exact ce stie
           telefonul si intoarce el diferenta — asa nu depindem de ceasul
           local si nici de ordinea in care ajung raspunsurile. */
        val cursorBefore = app.lastCommissionId

        return@withLock when (val res = app.apiSummary(cursorBefore.takeIf { it >= 0 })) {
            is TixelloApi.Result.Err -> {
                Log.w(TAG, "sync esuat: ${res.message}")
                app.lastError = res.message
                /* Widget-ul ramane pe ultimele cifre bune, dar isi marcheaza
                   vechimea — mai bine o cifra veche marcata decat un ecran gol. */
                WidgetRenderer.refreshAll(app)
                Outcome.Failed(res.message)
            }

            is TixelloApi.Result.Ok -> {
                val (raw, snapshot) = res.value

                app.snapshotJson = raw
                app.syncedAt = System.currentTimeMillis()
                app.lastError = null

                val fresh = if (cursorBefore < 0) {
                    /* Prima sincronizare: doar invatam unde suntem. Fara ea,
                       instalarea aplicatiei ar declansa 5 alerte deodata. */
                    emptyList()
                } else {
                    snapshot.newCommissions.filter { it.id > cursorBefore }
                }

                val newCursor = maxOf(
                    snapshot.lastCommissionId,
                    snapshot.commissions.maxOfOrNull { it.id } ?: 0L,
                    cursorBefore
                )
                app.lastCommissionId = newCursor

                var alerted = 0
                if (fresh.isNotEmpty() && allowAlerts && app.alertsEnabled) {
                    Notifier.notifyCommissions(app, fresh)
                    alerted = fresh.size
                }

                WidgetRenderer.refreshAll(app)
                Outcome.Ok(snapshot, alerted)
            }
        }
    }
}
