package ro.tixello.app;

import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.webkit.WebView;

import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;

/**
 * Chrome-ul de sistem al aplicatiei.
 *
 * Ce vrem:
 *   1. fundalul paginii sa urce SUB bara de status, ca ora si iconitele de
 *      sistem sa stea peste acelasi fundal ca zona de titlu (nu peste o banda
 *      gri separata);
 *   2. continutul sa NU intre sub ora/iconite — de asta trimitem inaltimea lor
 *      in CSS, ca `--safe-top`, si o folosim ca spatiu inainte de titlu;
 *   3. bara de navigatie de jos sa se ascunda singura la intrarea in aplicatie
 *      si sa reapara doar la swipe.
 *
 * De ce nu din CSS: WebView-ul de pe Android NU raporteaza fiabil
 * `env(safe-area-inset-*)`. Le citim nativ si le injectam ca variabile CSS.
 *
 * Incercarea anterioara inseta view-ul radacina, ceea ce lasa deasupra o banda
 * cu fundalul ferestrei (griul raportat) in loc sa lase fundalul paginii sa
 * urce pana sus.
 */
public class MainActivity extends BridgeActivity {

    /** Ultimele margini citite, in px CSS. Le retinem ca sa le putem RE-injecta
     *  dupa fiecare incarcare de pagina: variabilele CSS stau pe <html> si se
     *  pierd la reload (aplicarea unui bundle OTA, de exemplu), iar listener-ul
     *  de insets nu se mai declanseaza daca marginile n-au variat. */
    private int safeTop = 0;
    private int safeBottom = 0;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // 1. edge-to-edge: fereastra nu mai insereaza singura marginile
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        getWindow().setStatusBarColor(Color.TRANSPARENT);
        getWindow().setNavigationBarColor(Color.TRANSPARENT);

        // 3. ascundem bara de navigatie; reapare temporar la swipe dinspre margine
        WindowInsetsControllerCompat controller =
            WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        controller.setSystemBarsBehavior(
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        );
        controller.hide(WindowInsetsCompat.Type.navigationBars());
        // iconitele barei de status: albe, pentru ca aplicatia e dark-first
        controller.setAppearanceLightStatusBars(false);

        // 2. trimitem marginile in CSS
        final View root = findViewById(android.R.id.content);
        ViewCompat.setOnApplyWindowInsetsListener(root, (v, windowInsets) -> {
            Insets bars = windowInsets.getInsets(
                WindowInsetsCompat.Type.statusBars()
                    | WindowInsetsCompat.Type.navigationBars()
                    | WindowInsetsCompat.Type.displayCutout()
            );
            float d = getResources().getDisplayMetrics().density;
            safeTop = Math.round(bars.top / d);
            safeBottom = Math.round(bars.bottom / d);
            applyInsets();
            // NU consumam: alte view-uri pot avea nevoie de ele
            return windowInsets;
        });

        // Variabilele CSS traiesc pe <html> si dispar la orice reincarcare de
        // pagina (de ex. cand se aplica un bundle OTA). Listener-ul de insets nu
        // se mai declanseaza dupa reload daca marginile n-au variat, asa ca
        // fara asta ecranele porneau cu --safe-top = 0 si urcau sub ora.
        getBridge().addWebViewListener(new WebViewListener() {
            @Override
            public void onPageLoaded(WebView webView) {
                applyInsets();
            }
        });
    }

    /** Scrie --safe-top / --safe-bottom pe <html>, in px CSS. */
    private void applyInsets() {
        if (getBridge() == null || getBridge().getWebView() == null) return;
        final WebView wv = getBridge().getWebView();
        final String js =
            "(function(){var r=document.documentElement.style;" +
            "r.setProperty('--safe-top','" + safeTop + "px');" +
            "r.setProperty('--safe-bottom','" + safeBottom + "px');})()";
        wv.post(() -> wv.evaluateJavascript(js, null));
    }

    /**
     * Tastele de volum comanda si sunetul feed-ului.
     *
     * WebView-ul nu vede tastele hardware si nu poate citi volumul sistemului,
     * deci fara punctul asta nativ butonul de mute din aplicatie si volumul
     * telefonului raman doua lucruri fara legatura: dai telefonul pe silentios
     * si short-ul continua sa cante.
     *
     * NU consumam evenimentul — `super` ruleaza mai departe, deci volumul chiar
     * se schimba. Citim valoarea DUPA schimbare (de aici `post`), fiindca in
     * momentul apasarii sistemul inca n-a aplicat-o.
     *
     * Aplicatia asculta `tixello:system-volume` si isi pune sunetul dupa el.
     */
    @Override
    public boolean onKeyDown(int keyCode, android.view.KeyEvent event) {
        boolean handled = super.onKeyDown(keyCode, event);

        if (keyCode == android.view.KeyEvent.KEYCODE_VOLUME_UP
            || keyCode == android.view.KeyEvent.KEYCODE_VOLUME_DOWN
            || keyCode == android.view.KeyEvent.KEYCODE_VOLUME_MUTE) {
            notifyVolume();
        }

        return handled;
    }

    private void notifyVolume() {
        if (getBridge() == null || getBridge().getWebView() == null) return;

        final WebView wv = getBridge().getWebView();
        final android.media.AudioManager am =
            (android.media.AudioManager) getSystemService(AUDIO_SERVICE);
        if (am == null) return;

        wv.postDelayed(() -> {
            int vol = am.getStreamVolume(android.media.AudioManager.STREAM_MUSIC);
            final String js =
                "window.dispatchEvent(new CustomEvent('tixello:system-volume',{detail:{volume:" + vol + "}}))";
            wv.evaluateJavascript(js, null);
        }, 60);
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        // bara de navigatie reapare dupa swipe / revenire din background — o reascundem
        if (hasFocus) {
            WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView())
                .hide(WindowInsetsCompat.Type.navigationBars());
        }
    }
}
