package com.tixello.widget

import android.app.Notification
import android.app.Service
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * Serviciul care tine ochii pe server.
 *
 * Un serviciu foreground e singurul mod in care o aplicatie de Android poate
 * verifica ceva la fiecare minut, la nesfarsit, fara push de la Google. Pretul
 * e notificarea permanenta — obligatorie prin sistem, dar pusa pe canal cu
 * importanta minima, deci sta jos in lista, fara sunet.
 *
 * La oprirea fortata din sistem, WorkManager-ul din [PollScheduler] il
 * reporneste la urmatoarea rulare periodica.
 */
class PollService : Service() {

    private var job: Job? = null
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        Notifier.ensureChannels(this)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            serviceEnabled = false
            stopSelf()

            return START_NOT_STICKY
        }

        startForegroundCompat(buildNotification())

        if (!isConfigured()) {
            stopSelf()

            return START_NOT_STICKY
        }

        /* Un singur ciclu de polling, oricate porniri ar veni (widget nou,
           boot, worker). Altfel doua bucle ar bate serverul in paralel. */
        if (job?.isActive != true) {
            job = scope.launch { loop() }
        }

        /* START_STICKY: daca sistemul ne omoara pentru memorie, ne reporneste
           singur cand se elibereaza. */
        return START_STICKY
    }

    private suspend fun loop() {
        while (scope.isActive) {
            val outcome = SyncEngine.sync(applicationContext)

            /* Notificarea serviciului devine si mini-dashboard: cat s-a
               incasat azi si cand a fost ultima verificare. */
            updateNotification()

            val seconds = when (outcome) {
                is SyncEngine.Outcome.NotConfigured -> {
                    stopSelf()

                    return
                }
                /* Dupa o eroare asteptam cel putin un minut: daca serverul e
                   picat, nu-l batem la 15 secunde. */
                is SyncEngine.Outcome.Failed -> maxOf(pollSeconds, 60)
                is SyncEngine.Outcome.Ok -> pollSeconds
            }

            delay(seconds * 1000L)
        }
    }

    private fun startForegroundCompat(notification: Notification) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(
                Notifier.NOTIFICATION_SERVICE,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
            )
        } else {
            startForeground(Notifier.NOTIFICATION_SERVICE, notification)
        }
    }

    private fun updateNotification() {
        val manager = getSystemService(android.app.NotificationManager::class.java) ?: return

        try {
            manager.notify(Notifier.NOTIFICATION_SERVICE, buildNotification())
        } catch (e: SecurityException) {
            /* Fara POST_NOTIFICATIONS nu putem actualiza textul, dar serviciul
               ramane pornit si widget-ul se actualizeaza oricum. */
        }
    }

    private fun buildNotification(): Notification {
        val snapshot = cachedSnapshot()
        val error = lastError

        val text = when {
            error != null -> getString(R.string.service_notification_error, error)
            snapshot != null -> getString(
                R.string.service_notification_text,
                Format.money(snapshot.revenueToday, snapshot.currency),
                Format.relativeTime(syncedAt)
            )
            else -> getString(R.string.service_notification_waiting)
        }

        val stopIntent = android.app.PendingIntent.getService(
            this,
            2,
            Intent(this, PollService::class.java).setAction(ACTION_STOP),
            android.app.PendingIntent.FLAG_UPDATE_CURRENT or android.app.PendingIntent.FLAG_IMMUTABLE
        )

        return NotificationCompat.Builder(this, Notifier.CHANNEL_SERVICE)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(getString(R.string.service_notification_title))
            .setContentText(text)
            .setOngoing(true)
            .setSilent(true)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setContentIntent(Notifier.openAppIntent(this))
            .addAction(0, getString(R.string.service_notification_stop), stopIntent)
            .build()
    }

    override fun onDestroy() {
        job?.cancel()
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        const val ACTION_STOP = "com.tixello.widget.ACTION_STOP_SERVICE"
    }
}
