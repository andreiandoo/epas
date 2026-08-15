package com.tixello.widget

import android.content.Context
import android.content.SharedPreferences

/**
 * Setarile si ultima stare cunoscuta.
 *
 * SharedPreferences simplu, in stocarea privata a aplicatiei: pe un telefon
 * ne-rootat, alta aplicatie nu poate citi fisierul. Token-ul e revocabil de pe
 * server (`php artisan tixello:widget-token --revoke=ID`), deci pierderea lui
 * odata cu telefonul se rezolva acolo, nu prin criptare locala.
 */
object Prefs {
    const val DEFAULT_POLL_SECONDS = 60
    const val MIN_POLL_SECONDS = 15
    const val MAX_POLL_SECONDS = 3600
}

private const val FILE = "tixello_widget"

private const val KEY_BASE_URL = "base_url"
private const val KEY_TOKEN = "token"
private const val KEY_POLL_SECONDS = "poll_seconds"
private const val KEY_ALERTS = "alerts_enabled"
private const val KEY_SOUND = "alert_sound"
private const val KEY_VIBRATE = "alert_vibrate"
private const val KEY_LAST_COMMISSION_ID = "last_commission_id"
private const val KEY_SNAPSHOT = "snapshot_json"
private const val KEY_SYNCED_AT = "synced_at"
private const val KEY_LAST_ERROR = "last_error"
private const val KEY_SERVICE_ON = "service_enabled"

private fun Context.prefs(): SharedPreferences =
    applicationContext.getSharedPreferences(FILE, Context.MODE_PRIVATE)

/** Adresa serverului, fara slash la final (ex. `https://core.tixello.com`). */
var Context.baseUrl: String
    get() = prefs().getString(KEY_BASE_URL, "").orEmpty()
    set(value) = prefs().edit().putString(KEY_BASE_URL, value.trim().trimEnd('/')).apply()

var Context.token: String
    get() = prefs().getString(KEY_TOKEN, "").orEmpty()
    set(value) = prefs().edit().putString(KEY_TOKEN, value.trim()).apply()

var Context.pollSeconds: Int
    get() = prefs().getInt(KEY_POLL_SECONDS, Prefs.DEFAULT_POLL_SECONDS)
    set(value) = prefs().edit()
        .putInt(KEY_POLL_SECONDS, value.coerceIn(Prefs.MIN_POLL_SECONDS, Prefs.MAX_POLL_SECONDS))
        .apply()

var Context.alertsEnabled: Boolean
    get() = prefs().getBoolean(KEY_ALERTS, true)
    set(value) = prefs().edit().putBoolean(KEY_ALERTS, value).apply()

var Context.alertSound: Boolean
    get() = prefs().getBoolean(KEY_SOUND, true)
    set(value) = prefs().edit().putBoolean(KEY_SOUND, value).apply()

var Context.alertVibrate: Boolean
    get() = prefs().getBoolean(KEY_VIBRATE, true)
    set(value) = prefs().edit().putBoolean(KEY_VIBRATE, value).apply()

/**
 * Cel mai mare ID de comision pe care telefonul l-a vazut. Cursorul care face
 * diferenta intre „comision nou" (suna) si „istoric" (doar afisaj).
 *
 * -1 = inca nu s-a sincronizat niciodata; prima sincronizare doar il seteaza,
 * fara sa alerteze pentru tot istoricul.
 */
var Context.lastCommissionId: Long
    get() = prefs().getLong(KEY_LAST_COMMISSION_ID, -1L)
    set(value) = prefs().edit().putLong(KEY_LAST_COMMISSION_ID, value).apply()

/** Ultimul payload primit, exact cum a venit. Widget-ul deseneaza din el. */
var Context.snapshotJson: String?
    get() = prefs().getString(KEY_SNAPSHOT, null)
    set(value) = prefs().edit().putString(KEY_SNAPSHOT, value).apply()

var Context.syncedAt: Long
    get() = prefs().getLong(KEY_SYNCED_AT, 0L)
    set(value) = prefs().edit().putLong(KEY_SYNCED_AT, value).apply()

var Context.lastError: String?
    get() = prefs().getString(KEY_LAST_ERROR, null)
    set(value) = prefs().edit().putString(KEY_LAST_ERROR, value).apply()

/** Daca urmarirea permanenta (serviciul foreground) e pornita de utilizator. */
var Context.serviceEnabled: Boolean
    get() = prefs().getBoolean(KEY_SERVICE_ON, false)
    set(value) = prefs().edit().putBoolean(KEY_SERVICE_ON, value).apply()

fun Context.isConfigured(): Boolean = baseUrl.isNotEmpty() && token.isNotEmpty()

/** Snapshot-ul salvat, deja parsat. Null cand nu s-a sincronizat niciodata. */
fun Context.cachedSnapshot(): Snapshot? = snapshotJson?.let { Snapshot.parse(it) }
