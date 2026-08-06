package com.aso.app;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.os.Build;
import android.util.Log;

/**
 * تصنيف جودة الوصلة الحالية.
 *
 * يُستخدم لاختيار حجم الجزء (chunk) عند الرفع:
 *  • وصلة سريعة → أجزاء كبيرة: عدد أقل من الرحلات، رفع أسرع بوضوح.
 *  • وصلة ضعيفة → أجزاء صغيرة: كل جزء ينجح بسرعة فيُثبَّت التقدّم أولاً بأول،
 *    وفشل جزء يكلّف إعادة إرسال كيلوبايتات لا ميجابايتات.
 *
 * القاعدة العامة: احتمال فشل الجزء يتناسب مع زمن إرساله، وكلفة الفشل تتناسب
 * مع حجمه. لذلك الجزء الكبير على وصلة ضعيفة أسوأ خيار ممكن.
 */
public class NetworkQuality {
    private static final String TAG = "NetworkQuality";

    public static final int WEAK   = 0;
    public static final int NORMAL = 1;
    public static final int FAST   = 2;

    /** أقل من هذا يُعتبر وصلة ضعيفة (كيلوبت/ثانية صعوداً). */
    private static final int WEAK_KBPS = 600;
    /** أعلى من هذا يُعتبر وصلة سريعة. */
    private static final int FAST_KBPS = 5000;

    /**
     * يصنّف الوصلة الحالية. يعود WEAK عند تعذّر التحديد — الافتراض المتحفّظ أأمن.
     */
    public static int classify(Context context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return WEAK;

        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return WEAK;

            Network network = cm.getActiveNetwork();
            if (network == null) return WEAK;

            NetworkCapabilities caps = cm.getNetworkCapabilities(network);
            if (caps == null) return WEAK;

            // الوصلة غير الموثّقة تتصرّف كالضعيفة حتى لو ادّعت سرعة عالية.
            if (!caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)) {
                return WEAK;
            }

            int upKbps = caps.getLinkUpstreamBandwidthKbps();
            if (upKbps <= 0) return NORMAL;

            if (upKbps < WEAK_KBPS) {
                Log.d(TAG, "وصلة ضعيفة: " + upKbps + " كيلوبت/ث صعوداً");
                return WEAK;
            }
            if (upKbps >= FAST_KBPS) {
                Log.d(TAG, "وصلة سريعة: " + upKbps + " كيلوبت/ث صعوداً");
                return FAST;
            }
            return NORMAL;
        } catch (Exception e) {
            Log.w(TAG, "تعذّر تقدير جودة الشبكة: " + e.getMessage());
            return WEAK;
        }
    }

    /** توافق للخلف مع النداءات القديمة. */
    public static boolean isSlow(Context context) {
        return classify(context) == WEAK;
    }
}
