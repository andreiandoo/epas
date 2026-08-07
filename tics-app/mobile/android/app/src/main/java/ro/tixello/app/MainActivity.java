package ro.tixello.app;

import android.graphics.Color;
import android.os.Bundle;
import android.view.View;

import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

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
            applyInsets(Math.round(bars.top / d), Math.round(bars.bottom / d));
            // NU consumam: alte view-uri pot avea nevoie de ele
            return windowInsets;
        });
    }

    /** Scrie --safe-top / --safe-bottom pe <html>, in px CSS. */
    private void applyInsets(final int top, final int bottom) {
        if (getBridge() == null || getBridge().getWebView() == null) return;
        final String js =
            "(function(){var r=document.documentElement.style;" +
            "r.setProperty('--safe-top','" + top + "px');" +
            "r.setProperty('--safe-bottom','" + bottom + "px');})()";
        getBridge().getWebView().post(() -> getBridge().getWebView().evaluateJavascript(js, null));
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
