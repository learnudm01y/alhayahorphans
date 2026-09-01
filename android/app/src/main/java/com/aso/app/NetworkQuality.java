package com.aso.app;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.os.Build;
import android.util.Log;

/**
 * تقدير جودة الوصلة الحالية.
 *
 * يُستخدم لاختيار حجم الجزء (chunk) عند الرفع: على وصلة ضعيفة يكون الجزء
 * الكبير كارثة — كل فشل يُهدر ما رُفع منه بالكامل، واحتمال الفشل يتناسب مع
 * حجم الجزء. تقسيم الرفع إلى أجزاء صغيرة يجعل التقدّم يُحفظ باستمرار.
 */
public class NetworkQuality {
    private static final String TAG = "NetworkQuality";

    /** أقل من هذا يُعتبر وصلة ضعيفة (كيلوبت/ثانية). */
    private static final int SLOW_KBPS = 600;

    /**
     * هل الوصلة الحالية بطيئة أو غير مؤكدة؟
     * يعود true أيضاً عند تعذّر التحديد — الافتراض المتحفّظ أأمن.
     */
    public static boolean isSlow(Context context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) return true;

        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return true;

            Network network = (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M)
                ? cm.getActiveNetwork() : null;
            if (network == null) return true;

            NetworkCapabilities caps = cm.getNetworkCapabilities(network);
            if (caps == null) return true;

            int upKbps = caps.getLinkUpstreamBandwidthKbps();
            if (upKbps <= 0) return true;

            // الوصلة غير الموثّقة تتصرّف كالضعيفة حتى لو ادّعت سرعة عالية.
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M
                && !caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)) {
                return true;
            }

            boolean slow = upKbps < SLOW_KBPS;
            if (slow) {
                Log.d(TAG, "وصلة ضعيفة: " + upKbps + " كيلوبت/ث صعوداً");
            }
            return slow;
        } catch (Exception e) {
            Log.w(TAG, "تعذّر تقدير جودة الشبكة: " + e.getMessage());
            return true;
        }
    }
}
