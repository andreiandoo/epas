package com.tixello.widget

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/**
 * Alertele de comision — sunet si vibratie, fara Firebase.
 *
 * De ce patru canale si nu unul cu setari schimbabile: din Android 8, odata
 * creat un canal, aplicatia nu-i mai poate schimba sunetul sau vibratia
 * (numai utilizatorul, din Setari). Ca sa functioneze comutatoarele din
 * aplicatie, fiecare combinatie sunet/vibratie are canalul ei, iar comutatorul
 * alege canalul.
 *
 * Modul telefonului (silentios / doar vibratii / sonor) e respectat de sistem
 * automat: pe „doar vibratii" notificarea cu sunet vibreaza, nu suna.
 */
object Notifier {

    const val CHANNEL_SERVICE = "tixello_service"
    private const val CHANNEL_FULL = "tixello_commissions_full"
    private const val CHANNEL_SOUND = "tixello_commissions_sound"
    private const val CHANNEL_VIBRATE = "tixello_commissions_vibrate"
    private const val CHANNEL_SILENT = "tixello_commissions_silent"

    /** ID-uri fixe pentru notificarea serviciului si pentru rezumat. */
    const val NOTIFICATION_SERVICE = 1
    private const val NOTIFICATION_SUMMARY = 2
    private const val GROUP_COMMISSIONS = "tixello_commissions"

    private val VIBRATION_PATTERN = longArrayOf(0, 250, 150, 250)

    fun ensureChannels(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val manager = context.getSystemService(NotificationManager::class.java) ?: return

        val sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val audio = AudioAttributes.Builder()
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .build()

        val channels = listOf(
            NotificationChannel(CHANNEL_FULL, "Comisioane (sunet + vibratie)", NotificationManager.IMPORTANCE_HIGH).apply {
                setSound(sound, audio)
                enableVibration(true)
                vibrationPattern = VIBRATION_PATTERN
            },
            NotificationChannel(CHANNEL_SOUND, "Comisioane (doar sunet)", NotificationManager.IMPORTANCE_HIGH).apply {
                setSound(sound, audio)
                enableVibration(false)
            },
            NotificationChannel(CHANNEL_VIBRATE, "Comisioane (doar vibratie)", NotificationManager.IMPORTANCE_HIGH).apply {
                setSound(null, null)
                enableVibration(true)
                vibrationPattern = VIBRATION_PATTERN
            },
            NotificationChannel(CHANNEL_SILENT, "Comisioane (silentios)", NotificationManager.IMPORTANCE_LOW).apply {
                setSound(null, null)
                enableVibration(false)
            },
            NotificationChannel(CHANNEL_SERVICE, "Urmarire in fundal", NotificationManager.IMPORTANCE_MIN).apply {
                setSound(null, null)
                enableVibration(false)
                setShowBadge(false)
            }
        )

        channels.forEach { manager.createNotificationChannel(it) }
    }

    /**
     * Cate o notificare per comision (grupate), plus un rezumat cand sunt mai
     * multe. Asa vezi din prima de la ce eveniment a venit fiecare, nu doar
     * „ai 3 comisioane noi".
     */
    fun notifyCommissions(context: Context, commissions: List<Commission>) {
        if (commissions.isEmpty()) return

        ensureChannels(context)

        val manager = NotificationManagerCompat.from(context)
        if (!manager.areNotificationsEnabled()) return

        val channel = channelFor(context)
        val currency = commissions.first().displayCurrency
        val total = commissions.sumOf { it.displayAmount }

        commissions.forEach { commission ->
            val text = listOfNotNull(commission.event, commission.source)
                .joinToString(" · ")

            val notification = NotificationCompat.Builder(context, channel)
                .setSmallIcon(R.drawable.ic_notification)
                .setContentTitle(
                    context.getString(
                        R.string.notif_commission_title,
                        Format.money(commission.displayAmount, commission.displayCurrency)
                    )
                )
                .setContentText(text)
                .setStyle(NotificationCompat.BigTextStyle().bigText(text))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_EVENT)
                .setAutoCancel(true)
                .setGroup(GROUP_COMMISSIONS)
                .setContentIntent(openAppIntent(context))
                .setWhen(System.currentTimeMillis())
                .build()

            /* ID-ul comisionului ca ID de notificare: aceeasi incasare nu
               poate aparea de doua ori, chiar daca sincronizarea se repeta. */
            manager.notifyChecked(commission.id.toInt(), notification)
        }

        if (commissions.size > 1) {
            val summary = NotificationCompat.Builder(context, channel)
                .setSmallIcon(R.drawable.ic_notification)
                .setContentTitle(
                    context.getString(
                        R.string.notif_commission_summary_title,
                        commissions.size,
                        Format.money(total, currency)
                    )
                )
                .setGroup(GROUP_COMMISSIONS)
                .setGroupSummary(true)
                .setAutoCancel(true)
                .setContentIntent(openAppIntent(context))
                .build()

            manager.notifyChecked(NOTIFICATION_SUMMARY, summary)
        }
    }

    private fun channelFor(context: Context): String = when {
        context.alertSound && context.alertVibrate -> CHANNEL_FULL
        context.alertSound -> CHANNEL_SOUND
        context.alertVibrate -> CHANNEL_VIBRATE
        else -> CHANNEL_SILENT
    }

    fun openAppIntent(context: Context): PendingIntent {
        val intent = Intent(context, SetupActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)

        return PendingIntent.getActivity(
            context,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    /**
     * `notify` arunca SecurityException pe Android 13+ fara POST_NOTIFICATIONS.
     * O alerta pierduta nu are voie sa omoare serviciul de urmarire.
     */
    private fun NotificationManagerCompat.notifyChecked(
        id: Int,
        notification: android.app.Notification
    ) {
        try {
            notify(id, notification)
        } catch (e: SecurityException) {
            android.util.Log.w("TixelloNotifier", "notificare respinsa: ${e.message}")
        }
    }
}
