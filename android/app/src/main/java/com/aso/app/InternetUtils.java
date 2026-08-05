package com.aso.app;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkInfo;
import android.os.Build;
import android.util.Log;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * فحص الاتصال بالإنترنت.
 *
 * ⚠️ ملاحظة مهمة على النسخة السابقة:
 * كانت تبني القرار على "ping" متزامن مدته ٣ ثوانٍ إلى
 * http://clients3.google.com/generate_204، وتشترط الرمز 204 مع طول محتوى صفر.
 * على شبكة ضعيفة (٢G / حافة تغطية / واي فاي مزدحم) يفشل هذا الطلب باستمرار،
 * فكان النظام كله يرفض إرسال بايت واحد رغم أن الاتصال يعمل فعلاً — وهذا سبب
 * مباشر لشكوى «لا يعمل إطلاقاً عندما يكون الإنترنت ضعيفاً». كما أن الاعتماد
 * على مضيف Google يجعل المنظومة تتعطّل حيث يكون محجوباً.
 *
 * الحل: الاعتماد على فحص النظام نفسه (NET_CAPABILITY_VALIDATED) — وهو نفس
 * الفحص الذي يجريه أندرويد للكشف عن البوابات المقيّدة، لكنه مُخبّأ ومجاني ولا
 * يستهلك وقتاً. بقي الـ ping كخيار احتياطي للأنظمة القديمة فقط، وبمهلة كريمة،
 * ولا يُستخدم كبوابة تمنع الرفع.
 */
public class InternetUtils {
    private static final String TAG = "InternetUtils";

    /** مهلة كريمة: الشبكة الضعيفة بطيئة، وليست معطّلة. */
    private static final int LEGACY_PING_TIMEOUT_MS = 10000;

    /**
     * هل هناك اتصال يُرجَّح أنه يصل للإنترنت؟
     *
     * تُستخدم للقرارات «اللينة» فقط (مثل تأجيل عمل غير عاجل). لا تُستخدم كبوابة
     * تمنع محاولة الرفع: المرجع الحقيقي لنجاح الرفع هو الرفع نفسه.
     */
    public static boolean isInternetActuallyAvailable(Context context) {
        if (!isNetworkConnected(context)) {
            Log.d(TAG, "لا يوجد اتصال شبكة أساسي.");
            return false;
        }

        // أندرويد ٦ فأحدث: النظام يخبرنا مباشرةً إن كانت الشبكة موثّقة الوصول.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Boolean validated = isNetworkValidated(context);
            if (validated != null) {
                if (!validated) {
                    Log.d(TAG, "الشبكة متصلة لكن النظام لم يوثّق وصولها للإنترنت (بوابة مقيّدة؟).");
                }
                return validated;
            }
        }

        // أنظمة أقدم فقط: ping احتياطي بمهلة كريمة.
        return isPingSuccessful();
    }

    /**
     * هل يوجد اتصال شبكة على الإطلاق؟
     *
     * هذه هي البوابة الصحيحة قبل محاولة الرفع: إن لم تكن هناك شبكة أصلاً فلا
     * فائدة من المحاولة، أمّا إن وُجدت شبكة — مهما كانت ضعيفة — فالمحاولة واجبة.
     */
    public static boolean hasAnyNetwork(Context context) {
        return isNetworkConnected(context);
    }

    /**
     * الفحص الأساسي من أندرويد (متصل بموجّه، أو ببرج اتصال…)
     */
    private static boolean isNetworkConnected(Context context) {
        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return false;

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                Network network = cm.getActiveNetwork();
                if (network == null) return false;
                NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
                return capabilities != null &&
                       capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
            } else {
                NetworkInfo networkInfo = cm.getActiveNetworkInfo();
                return networkInfo != null && networkInfo.isConnected();
            }
        } catch (Exception e) {
            Log.w(TAG, "تعذّر فحص قدرات الشبكة: " + e.getMessage());
            return false;
        }
    }

    /**
     * فحص النظام للوصول الفعلي. يُرجع null إذا تعذّر تحديد ذلك.
     */
    private static Boolean isNetworkValidated(Context context) {
        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return null;
            Network network = cm.getActiveNetwork();
            if (network == null) return null;
            NetworkCapabilities capabilities = cm.getNetworkCapabilities(network);
            if (capabilities == null) return null;
            return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
        } catch (Exception e) {
            Log.w(TAG, "تعذّر قراءة NET_CAPABILITY_VALIDATED: " + e.getMessage());
            return null;
        }
    }

    /**
     * احتياطي للأنظمة القديمة فقط. أي ردّ صالح من خادم يعني وجود مسار للإنترنت.
     */
    private static boolean isPingSuccessful() {
        HttpURLConnection urlConnection = null;
        try {
            URL url = new URL("https://clients3.google.com/generate_204");
            urlConnection = (HttpURLConnection) url.openConnection();
            urlConnection.setRequestProperty("User-Agent", "Android");
            urlConnection.setRequestProperty("Connection", "close");
            urlConnection.setConnectTimeout(LEGACY_PING_TIMEOUT_MS);
            urlConnection.setReadTimeout(LEGACY_PING_TIMEOUT_MS);
            urlConnection.connect();

            int responseCode = urlConnection.getResponseCode();
            // أي ردّ صالح يعني أننا وصلنا لخادم ما. لا نشترط 204 بالضبط:
            // الاشتراط الصارم كان يُسقط شبكات سليمة خلف وكيل أو ذاكرة تخزين.
            boolean reachable = responseCode > 0 && responseCode < 500;
            if (!reachable) {
                Log.d(TAG, "الـ ping الاحتياطي رجع برمز " + responseCode);
            }
            return reachable;
        } catch (IOException e) {
            Log.d(TAG, "فشل الـ ping الاحتياطي: " + e.getMessage());
            return false;
        } finally {
            if (urlConnection != null) {
                urlConnection.disconnect();
            }
        }
    }
}
