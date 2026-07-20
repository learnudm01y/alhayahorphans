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

public class InternetUtils {
    private static final String TAG = "InternetUtils";

    /**
     * Checks if the device has a network connection (WiFi/Mobile Data)
     * AND if it can actually reach the internet by pinging a reliable server.
     * 
     * @param context Application context
     * @return true if actual internet access is verified, false otherwise
     */
    public static boolean isInternetActuallyAvailable(Context context) {
        if (!isNetworkConnected(context)) {
            Log.e(TAG, "❌ No basic network connection detected.");
            return false;
        }

        return isPingSuccessful();
    }

    /**
     * Basic Android check (connected to a router, cell tower, etc.)
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
            Log.w(TAG, "Failed to check network capabilities: " + e.getMessage());
            return false;
        }
    }

    /**
     * Fast ping to a highly available server (Google DNS or Google.com)
     */
    private static boolean isPingSuccessful() {
        HttpURLConnection urlConnection = null;
        try {
            // Using a very fast request to generate a 204 No Content
            URL url = new URL("http://clients3.google.com/generate_204");
            urlConnection = (HttpURLConnection) url.openConnection();
            urlConnection.setRequestProperty("User-Agent", "Android");
            urlConnection.setRequestProperty("Connection", "close");
            urlConnection.setConnectTimeout(3000); // 3 seconds timeout
            urlConnection.setReadTimeout(3000); // 3 seconds timeout
            urlConnection.connect();

            int responseCode = urlConnection.getResponseCode();
            if (responseCode == 204 && urlConnection.getContentLength() == 0) {
                Log.d(TAG, "✅ Actual Internet Verified (Ping successful)");
                return true;
            } else {
                Log.e(TAG, "❌ Connected to network, but internet unreachable (Captive portal or blocked). Code: " + responseCode);
                return false;
            }
        } catch (IOException e) {
            Log.e(TAG, "❌ Connected to network, but ping failed (No real internet): " + e.getMessage());
            return false;
        } finally {
            if (urlConnection != null) {
                urlConnection.disconnect();
            }
        }
    }
}
