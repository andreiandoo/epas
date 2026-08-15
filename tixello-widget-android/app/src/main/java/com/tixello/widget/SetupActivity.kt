package com.tixello.widget

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.EditText
import android.widget.Spinner
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.SwitchCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.tixello.widget.widget.WidgetRenderer
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Singurul ecran al aplicatiei: adresa serverului, token-ul, cat de des
 * intreaba si cum alerteaza.
 *
 * Restul timpului aplicatia nu se deschide deloc — se traieste in widget si in
 * notificari.
 */
class SetupActivity : AppCompatActivity() {

    /* Valorile din spinner-ul de interval, in secunde. */
    private val intervals = listOf(15, 30, 60, 120, 300, 900)

    private lateinit var urlInput: EditText
    private lateinit var tokenInput: EditText
    private lateinit var intervalSpinner: Spinner
    private lateinit var alertsSwitch: SwitchCompat
    private lateinit var soundSwitch: SwitchCompat
    private lateinit var vibrateSwitch: SwitchCompat
    private lateinit var statusView: TextView
    private lateinit var numbersView: TextView

    private val notificationPermission = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (!granted) {
            toast(getString(R.string.setup_notifications_denied))
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_setup)

        urlInput = findViewById(R.id.input_url)
        tokenInput = findViewById(R.id.input_token)
        intervalSpinner = findViewById(R.id.spinner_interval)
        alertsSwitch = findViewById(R.id.switch_alerts)
        soundSwitch = findViewById(R.id.switch_sound)
        vibrateSwitch = findViewById(R.id.switch_vibrate)
        statusView = findViewById(R.id.text_status)
        numbersView = findViewById(R.id.text_numbers)

        findViewById<TextView>(R.id.text_version).text =
            getString(R.string.setup_version, BuildConfig.VERSION_NAME)

        setUpIntervalSpinner()
        bindSwitches()

        findViewById<Button>(R.id.button_save).setOnClickListener { saveAndTest() }
        findViewById<Button>(R.id.button_start).setOnClickListener { startTracking() }
        findViewById<Button>(R.id.button_stop).setOnClickListener { stopTracking() }
        findViewById<Button>(R.id.button_refresh).setOnClickListener { syncNow() }
        findViewById<Button>(R.id.button_battery).setOnClickListener { openBatterySettings() }
        findViewById<Button>(R.id.button_notification_settings).setOnClickListener { openNotificationSettings() }

        Notifier.ensureChannels(this)
        requestNotificationPermissionIfNeeded()
    }

    override fun onResume() {
        super.onResume()

        urlInput.setText(baseUrl)
        tokenInput.setText(token)
        intervalSpinner.setSelection(intervals.indexOf(pollSeconds).coerceAtLeast(0))
        alertsSwitch.isChecked = alertsEnabled
        soundSwitch.isChecked = alertSound
        vibrateSwitch.isChecked = alertVibrate

        renderStatus()
    }

    private fun setUpIntervalSpinner() {
        val labels = intervals.map { seconds ->
            if (seconds < 60) getString(R.string.setup_interval_seconds, seconds)
            else getString(R.string.setup_interval_minutes, seconds / 60)
        }

        intervalSpinner.adapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_dropdown_item,
            labels
        )

        intervalSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: android.view.View?, position: Int, id: Long) {
                pollSeconds = intervals[position]
            }

            override fun onNothingSelected(parent: AdapterView<*>?) = Unit
        }
    }

    private fun bindSwitches() {
        alertsSwitch.setOnCheckedChangeListener { _, checked -> alertsEnabled = checked }
        soundSwitch.setOnCheckedChangeListener { _, checked -> alertSound = checked }
        vibrateSwitch.setOnCheckedChangeListener { _, checked -> alertVibrate = checked }
    }

    private fun saveAndTest() {
        val url = urlInput.text.toString().trim().trimEnd('/')
        val tokenValue = tokenInput.text.toString().trim()

        if (url.isEmpty() || tokenValue.isEmpty()) {
            toast(getString(R.string.setup_missing_fields))

            return
        }

        if (!url.startsWith("https://") && !url.startsWith("http://")) {
            toast(getString(R.string.setup_url_scheme))

            return
        }

        baseUrl = url
        token = tokenValue

        statusView.text = getString(R.string.setup_checking)

        lifecycleScope.launch {
            val result = withContext(Dispatchers.IO) { TixelloApi.ping(url, tokenValue) }

            when (result) {
                is TixelloApi.Result.Ok -> {
                    statusView.text = result.value
                    /* Prima sincronizare invata cursorul si umple widget-ul. */
                    syncNow()
                }
                is TixelloApi.Result.Err -> statusView.text =
                    getString(R.string.setup_check_failed, result.message)
            }
        }
    }

    private fun startTracking() {
        if (!isConfigured()) {
            toast(getString(R.string.setup_missing_fields))

            return
        }

        requestNotificationPermissionIfNeeded()
        PollScheduler.start(this)
        toast(getString(R.string.setup_started))
        renderStatus()
    }

    private fun stopTracking() {
        PollScheduler.stop(this)
        toast(getString(R.string.setup_stopped))
        renderStatus()
    }

    private fun syncNow() {
        statusView.text = getString(R.string.setup_syncing)

        lifecycleScope.launch {
            val outcome = withContext(Dispatchers.IO) {
                /* Sincronizarea manuala nu alerteaza: te uiti oricum la ecran,
                   iar un sunet la fiecare apasare pe „Actualizeaza" ar fi doar
                   zgomot. Cursorul avanseaza normal. */
                SyncEngine.sync(this@SetupActivity, allowAlerts = false)
            }

            statusView.text = when (outcome) {
                is SyncEngine.Outcome.Ok -> getString(R.string.setup_sync_ok, Format.relativeTime(syncedAt))
                is SyncEngine.Outcome.Failed -> getString(R.string.setup_check_failed, outcome.message)
                is SyncEngine.Outcome.NotConfigured -> getString(R.string.setup_missing_fields)
            }

            WidgetRenderer.refreshAll(this@SetupActivity)
            renderNumbers()
        }
    }

    private fun renderStatus() {
        val tracking = serviceEnabled

        findViewById<TextView>(R.id.text_tracking).text = getString(
            if (tracking) R.string.setup_tracking_on else R.string.setup_tracking_off
        )

        renderNumbers()
    }

    /** Aceleasi cifre ca in widget, ca sa poti verifica fara sa iesi pe ecran. */
    private fun renderNumbers() {
        val snapshot = cachedSnapshot()

        if (snapshot == null) {
            numbersView.text = getString(R.string.widget_not_synced)

            return
        }

        val currency = snapshot.currency
        val commissions = snapshot.commissions.joinToString("\n") { commission ->
            "  • ${Format.money(commission.displayAmount, commission.displayCurrency)} — ${commission.event}"
        }

        numbersView.text = buildString {
            appendLine(getString(R.string.numbers_sales,
                Format.money(snapshot.salesTotal, currency),
                Format.money(snapshot.salesToday, currency)))
            appendLine(getString(R.string.numbers_orders,
                Format.count(snapshot.salesTotalOrders), Format.count(snapshot.salesTodayOrders)))
            appendLine(getString(R.string.numbers_tickets,
                Format.count(snapshot.ticketsTotal), Format.count(snapshot.ticketsToday)))
            appendLine(getString(R.string.numbers_customers,
                Format.count(snapshot.customersTotal), Format.count(snapshot.customersToday)))
            appendLine(getString(R.string.numbers_revenue,
                Format.money(snapshot.revenueTotal, currency),
                Format.money(snapshot.revenueToday, currency)))
            appendLine()
            appendLine(getString(R.string.numbers_last_commissions))
            append(if (commissions.isEmpty()) "  —" else commissions)
        }
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return

        val granted = ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED

        if (!granted) {
            notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    /**
     * Ecranul de optimizare a bateriei. Deschidem lista de setari, nu cerem
     * exceptarea direct: permisiunea pentru dialogul direct
     * (REQUEST_IGNORE_BATTERY_OPTIMIZATIONS) e restrictionata, iar aplicatia
     * asta oricum se instaleaza de mana.
     */
    private fun openBatterySettings() {
        val intent = Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS)

        try {
            startActivity(intent)
        } catch (e: Exception) {
            startActivity(
                Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                    .setData(Uri.fromParts("package", packageName, null))
            )
        }
    }

    private fun openNotificationSettings() {
        val intent = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                .putExtra(Settings.EXTRA_APP_PACKAGE, packageName)
        } else {
            Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                .setData(Uri.fromParts("package", packageName, null))
        }

        startActivity(intent)
    }

    private fun toast(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
    }
}
