package com.tixello.widget

import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

/**
 * Cine si cand trage cifrele.
 *
 * Doua mecanisme, intentionat suprapuse:
 *
 * 1. [PollService] — serviciu foreground, interogheaza la fiecare N secunde
 *    (implicit 60). El da senzatia de „instant" la comisioane. Cere o
 *    notificare permanenta, cu prioritate minima.
 * 2. WorkManager periodic — la 15 minute (minimul impus de Android). Prinde
 *    cazul in care sistemul opreste serviciul (memorie putina, „optimizare
 *    baterie" agresiva de la producator) si il reporneste.
 *
 * Fara Firebase, deci: telefonul intreaba, serverul nu impinge. La 60 s, o zi
 * intreaga inseamna ~1440 de cereri de cativa kilobytes — neglijabil.
 */
object PollScheduler {

    private const val WORK_PERIODIC = "tixello-widget-periodic-sync"
    private const val WORK_ONE_OFF = "tixello-widget-sync-now"

    /** Sincronizare acum: butonul de refresh, widget nou pus, revenire in aplicatie. */
    fun requestOneOffSync(context: Context) {
        if (!context.isConfigured()) return

        val request = OneTimeWorkRequestBuilder<SyncWorker>()
            .setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build()
            )
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            WORK_ONE_OFF,
            /* O cerere in curs e suficienta — apasarile repetate pe refresh nu
               trebuie sa se puna la coada una dupa alta. */
            ExistingWorkPolicy.KEEP,
            request
        )
    }

    /** Plasa de siguranta: la 15 min, chiar daca serviciul a fost oprit. */
    fun ensurePeriodicSync(context: Context) {
        val request = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES)
            .setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build()
            )
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            WORK_PERIODIC,
            ExistingPeriodicWorkPolicy.KEEP,
            request
        )
    }

    fun cancelPeriodicSync(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_PERIODIC)
    }

    fun startService(context: Context) {
        if (!context.isConfigured()) return

        val intent = Intent(context, PollService::class.java)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            context.startForegroundService(intent)
        } else {
            context.startService(intent)
        }
    }

    fun stopService(context: Context) {
        context.stopService(Intent(context, PollService::class.java))
    }

    /**
     * Pornirea completa: serviciul (daca e cerut), plasa periodica si o
     * sincronizare imediata.
     */
    fun start(context: Context) {
        if (!context.isConfigured()) return

        context.serviceEnabled = true
        ensurePeriodicSync(context)
        startService(context)
        requestOneOffSync(context)
    }

    fun stop(context: Context) {
        context.serviceEnabled = false
        stopService(context)
        cancelPeriodicSync(context)
    }

    /** Widget pus pe ecran: reia ce era pornit inainte. */
    fun onWidgetPlaced(context: Context) {
        if (!context.isConfigured()) return

        ensurePeriodicSync(context)
        if (context.serviceEnabled) startService(context)
        requestOneOffSync(context)
    }

    /** Repornire dupa boot / actualizare a aplicatiei. */
    fun onDeviceReady(context: Context) {
        if (!context.isConfigured()) return

        ensurePeriodicSync(context)
        if (context.serviceEnabled) startService(context)
    }
}

/**
 * Sincronizarea rulata de WorkManager. Aceeasi logica pe care o foloseste si
 * serviciul — vezi [SyncEngine].
 */
class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        /* Daca serviciul a murit intre timp, worker-ul il repune pe picioare —
           el e singurul care mai ruleaza in situatia asta. */
        if (applicationContext.serviceEnabled) {
            PollScheduler.startService(applicationContext)
        }

        return when (SyncEngine.sync(applicationContext)) {
            is SyncEngine.Outcome.Ok -> Result.success()
            is SyncEngine.Outcome.NotConfigured -> Result.success()
            /* `retry` doar pentru esecuri de retea: WorkManager reincearca cu
               backoff, fara sa consume baterie in bucla. */
            is SyncEngine.Outcome.Failed -> if (runAttemptCount < 3) Result.retry() else Result.success()
        }
    }
}
