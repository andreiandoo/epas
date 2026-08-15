package com.tixello.widget

import android.content.Context
import org.json.JSONObject
import java.io.BufferedReader
import java.net.HttpURLConnection
import java.net.URL

/**
 * Clientul HTTP. `HttpURLConnection` gol, fara biblioteci: aplicatia face un
 * singur GET la fiecare poll, iar un APK mic si fara dependinte de retea e mai
 * usor de compilat si de verificat.
 */
object TixelloApi {

    private const val CONNECT_TIMEOUT_MS = 15_000
    private const val READ_TIMEOUT_MS = 20_000

    sealed class Result<out T> {
        data class Ok<T>(val value: T) : Result<T>()
        data class Err(val message: String, val httpCode: Int? = null) : Result<Nothing>()
    }

    /** Verificare de token pentru ecranul de configurare. */
    fun ping(baseUrl: String, token: String): Result<String> {
        return when (val res = get(baseUrl, token, "/api/tixello-widget/ping")) {
            is Result.Err -> res
            is Result.Ok -> {
                val name = res.value.optString("token_name", "")
                Result.Ok(if (name.isEmpty()) "Token valid" else "Token valid: $name")
            }
        }
    }

    /**
     * Cifrele + ultimele comisioane.
     *
     * @param sinceCommissionId cursorul telefonului; `null` la prima rulare,
     *        ca serverul sa nu marcheze tot istoricul drept „nou".
     * @return perechea (payload brut pentru cache, snapshot parsat).
     */
    fun summary(
        baseUrl: String,
        token: String,
        sinceCommissionId: Long?,
        limit: Int = 5
    ): Result<Pair<String, Snapshot>> {
        val query = buildString {
            append("?limit=").append(limit)
            if (sinceCommissionId != null && sinceCommissionId >= 0) {
                append("&since_commission_id=").append(sinceCommissionId)
            }
        }

        return when (val res = get(baseUrl, token, "/api/tixello-widget/summary$query")) {
            is Result.Err -> res
            is Result.Ok -> {
                val data = res.value.optJSONObject("data")
                    ?: return Result.Err("Raspuns fara „data”")
                Result.Ok(data.toString() to Snapshot.parse(data))
            }
        }
    }

    private fun get(baseUrl: String, token: String, path: String): Result<JSONObject> {
        if (baseUrl.isBlank()) return Result.Err("Adresa serverului lipseste")
        if (token.isBlank()) return Result.Err("Token-ul lipseste")

        val url = try {
            URL(baseUrl.trimEnd('/') + path)
        } catch (e: Exception) {
            return Result.Err("Adresa serverului e invalida")
        }

        var conn: HttpURLConnection? = null
        return try {
            val connection = (url.openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = CONNECT_TIMEOUT_MS
                readTimeout = READ_TIMEOUT_MS
                setRequestProperty("Authorization", "Bearer $token")
                setRequestProperty("Accept", "application/json")
                setRequestProperty("User-Agent", "TixelloWidget/${BuildConfig.VERSION_NAME} (Android)")
                instanceFollowRedirects = true
            }
            conn = connection

            val code = connection.responseCode
            val body = (if (code in 200..299) connection.inputStream else connection.errorStream)
                ?.bufferedReader()
                ?.use(BufferedReader::readText)
                .orEmpty()

            when {
                code == 401 -> Result.Err("Token respins de server", code)
                code == 429 -> Result.Err("Prea multe cereri — mareste intervalul", code)
                code !in 200..299 -> Result.Err(serverMessage(body) ?: "Eroare server ($code)", code)
                body.isBlank() -> Result.Err("Raspuns gol de la server", code)
                else -> {
                    val json = JSONObject(body)
                    if (!json.optBoolean("success", false)) {
                        Result.Err(json.optString("message", "Cerere respinsa"), code)
                    } else {
                        Result.Ok(json)
                    }
                }
            }
        } catch (e: Exception) {
            Result.Err(e.message ?: e.javaClass.simpleName)
        } finally {
            conn?.disconnect()
        }
    }

    /** Laravel trimite erorile ca `{"message": "..."}` — le aratam ca atare. */
    private fun serverMessage(body: String): String? = try {
        JSONObject(body).optString("message", "").ifEmpty { null }
    } catch (e: Exception) {
        null
    }
}

/** Scurtatura pentru apelurile care iau setarile din Prefs. */
fun Context.apiSummary(sinceCommissionId: Long?): TixelloApi.Result<Pair<String, Snapshot>> =
    TixelloApi.summary(baseUrl, token, sinceCommissionId)
