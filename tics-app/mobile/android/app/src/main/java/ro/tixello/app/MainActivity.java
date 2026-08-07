package ro.tixello.app;

import android.os.Bundle;
import android.view.View;

import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.getcapacitor.BridgeActivity;

/**
 * Aplica marginile de siguranta ale sistemului direct pe view-ul radacina.
 *
 * DE CE NATIV, NU DIN CSS:
 * aplicatia tinteste SDK 35, iar Android 15 impune edge-to-edge — continutul
 * se deseneaza sub bara de status si sub bara de navigatie. WebView-ul de pe
 * Android NU raporteaza fiabil `env(safe-area-inset-*)`, deci solutia din CSS
 * lasa butoanele de jos ale aplicatiei sub butoanele de sistem.
 *
 * Aici insetam view-ul o singura data si tot layout-ul web ramane exact ca in
 * prototip, care presupune un viewport dreptunghiular complet vizibil.
 *
 * Se folosesc `systemBars` (status + navigatie, inclusiv bara de gesturi) si
 * `displayCutout` (notch/punch-hole in orientare landscape).
 */
public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        final View root = findViewById(android.R.id.content);
        ViewCompat.setOnApplyWindowInsetsListener(root, (v, windowInsets) -> {
            Insets bars = windowInsets.getInsets(
                WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout()
            );
            v.setPadding(bars.left, bars.top, bars.right, bars.bottom);
            return WindowInsetsCompat.CONSUMED;
        });
    }
}
