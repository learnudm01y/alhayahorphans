package com.aso.app;

import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.ValueCallback;
import com.getcapacitor.Bridge;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

public class IndexedDBReader {
    private static final String TAG = "IndexedDBReader";
    private static IndexedDBReader instance;
    private Context context;
    private Bridge capacitorBridge;
    private String cachedAssociationName = null;
    private String cachedPersonName = null;

    public static IndexedDBReader getInstance(Context context, Bridge bridge) {
        if (instance == null) {
            instance = new IndexedDBReader(context, bridge);
        }
        return instance;
    }

    private IndexedDBReader(Context context, Bridge bridge) {
        this.context = context.getApplicationContext();
        this.capacitorBridge = bridge;
        Log.e(TAG, "IndexedDBReader initialized - Bridge: " + (bridge != null) + ", WebView: " + (bridge != null && bridge.getWebView() != null));
    }

    public MappingData getSponsorshipData(int sponsorshipId) {
        Log.e(TAG, "getSponsorshipData called for ID: " + sponsorshipId);

        if (capacitorBridge == null || capacitorBridge.getWebView() == null) {
            Log.e(TAG, "ERROR: Bridge or WebView is NULL!");
            return new MappingData("General", "unknown");
        }

        try {
            final MappingData[] result = new MappingData[1];
            final CountDownLatch latch = new CountDownLatch(1);
            cachedAssociationName = null;
            cachedPersonName = null;

            new Handler(Looper.getMainLooper()).post(() -> {
                // Step 1: Execute async function and save to global variable
                StringBuilder js = new StringBuilder();
                js.append("window._idbResult_").append(sponsorshipId).append(" = null; ");
                js.append("(async function() {");
                js.append("  try {");
                js.append("    console.log('[IndexedDBReader] Query ID: ").append(sponsorshipId).append("');");
                js.append("    const db = window.SyncService?.db || window.db || window.$db;");
                js.append("    if (!db) { console.error('[IndexedDBReader] Database not found'); return {error: 'db_undefined'}; }");
                js.append("    console.log('[IndexedDBReader] Database found');");
                js.append("    const tx = db.transaction(['sponsorships', 'sponsors'], 'readonly');");
                js.append("    const sponsorshipsStore = tx.objectStore('sponsorships');");
                js.append("    const sponsorsStore = tx.objectStore('sponsors');");
                js.append("    const spReq = sponsorshipsStore.get(").append(sponsorshipId).append(");");
                js.append("    const sp = await new Promise((res, rej) => { spReq.onsuccess = () => res(spReq.result); spReq.onerror = () => rej(spReq.error); });");
                js.append("    if (!sp) { console.warn('[IndexedDBReader] Not found'); return {error: 'not_found'}; }");
                js.append("    console.log('[IndexedDBReader] Found, sponsor_id:', sp.sponsor_id);");
                js.append("    const assocReq = sponsorsStore.get(sp.sponsor_id);");
                js.append("    const assoc = await new Promise((res, rej) => { assocReq.onsuccess = () => res(assocReq.result); assocReq.onerror = () => rej(assocReq.error); });");
                js.append("    const result = { associationName: assoc?.name || 'General', personName: sp.orphan_name || sp.person_name || 'unknown' };");
                js.append("    console.log('[IndexedDBReader] Result:', JSON.stringify(result));");
                js.append("    return result;");
                js.append("  } catch(e) {");
                js.append("    console.error('[IndexedDBReader] Exception:', e.message);");
                js.append("    return {error: e.message};");
                js.append("  }");
                js.append("})().then(r => { window._idbResult_").append(sponsorshipId).append(" = r; });");
                js.append("'started'");

                String jsCode = js.toString();
                Log.e(TAG, "Step 1: Starting async query (" + jsCode.length() + " chars)");

                capacitorBridge.getWebView().evaluateJavascript(jsCode, value -> {
                    Log.e(TAG, "Step 1 complete, waiting 300ms for Promise...");

                    // Step 2: Wait for Promise to complete, then read result
                    new Handler(Looper.getMainLooper()).postDelayed(() -> {
                        String readJs = "JSON.stringify(window._idbResult_" + sponsorshipId + " || {error: 'timeout'})";

                        capacitorBridge.getWebView().evaluateJavascript(readJs, resultValue -> {
                            Log.e(TAG, "Step 2: Read result: " + (resultValue != null ? resultValue.substring(0, Math.min(300, resultValue.length())) : "null"));

                            if (resultValue != null && !resultValue.equals("null")) {
                                try {
                                    String json = resultValue.replace("\\\"", "\"");
                                    if (json.startsWith("\"")) json = json.substring(1);
                                    if (json.endsWith("\"")) json = json.substring(0, json.length() - 1);

                                    org.json.JSONObject obj = new org.json.JSONObject(json);

                                    if (!obj.has("error")) {
                                        cachedAssociationName = obj.optString("associationName", "General");
                                        cachedPersonName = obj.optString("personName", "unknown");
                                        Log.e(TAG, "SUCCESS - Association: " + cachedAssociationName + ", Person: " + cachedPersonName);
                                    } else {
                                        Log.e(TAG, "ERROR from JS: " + obj.optString("error"));
                                    }
                                } catch (Exception e) {
                                    Log.e(TAG, "JSON parse error: " + e.getMessage());
                                }
                            }

                            // Cleanup
                            capacitorBridge.getWebView().evaluateJavascript("delete window._idbResult_" + sponsorshipId, null);
                            latch.countDown();
                        });
                    }, 300); // Wait 300ms for IndexedDB transaction
                });
            });

            boolean success = latch.await(1, TimeUnit.SECONDS);

            if (success && cachedAssociationName != null && cachedPersonName != null) {
                result[0] = new MappingData(cachedAssociationName, cachedPersonName);
                Log.e(TAG, "Completed successfully");
            } else {
                Log.e(TAG, "Timeout or no data - using defaults");
                result[0] = new MappingData("General", "unknown");
            }

            return result[0];

        } catch (Exception e) {
            Log.e(TAG, "Exception: " + e.getMessage());
            e.printStackTrace();
            return new MappingData("General", "unknown");
        }
    }

    public static class MappingData {
        public final String associationName;
        public final String personName;

        public MappingData(String associationName, String personName) {
            this.associationName = associationName != null ? associationName : "General";
            this.personName = personName != null ? personName : "unknown";
        }
    }
}
