package org.alhayah.sponsorships;

import android.os.Bundle;
import android.view.View;
import android.view.WindowManager;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Enable edge-to-edge display
        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);

        // Set status bar and navigation bar colors
        getWindow().setStatusBarColor(getResources().getColor(R.color.colorPrimaryDark, getTheme()));
        getWindow().setNavigationBarColor(getResources().getColor(R.color.colorPrimaryDark, getTheme()));

        // Make status bar icons light (for dark background)
        WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (controller != null) {
            controller.setAppearanceLightStatusBars(false);
            controller.setAppearanceLightNavigationBars(false);
        }
    }
}
