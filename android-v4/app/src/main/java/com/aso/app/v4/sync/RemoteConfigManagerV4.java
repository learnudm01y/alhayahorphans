package com.aso.app.v4.sync;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.ExistingWorkPolicy;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;

import java.util.concurrent.TimeUnit;

/**
 * Phase 7 — Staged Cutover remote-config consumer.
 *
 * Fetches GET /api/mobile/v4/config/flags each sync cycle and applies
 * legacy_sync_enabled to WorkManager at runtime:
 *   false → cancelUniqueWork on v3 engines (code is NEVER deleted)
 *   true  → re-schedule the same unique works (instant rollback)
 *
 * Cached flag is applied on cold start so cutover survives process death
 * before the next network fetch.
 */
public final class RemoteConfigManagerV4 {
    private static final String TAG = "RemoteConfigV4";
    private static final String PREFS = "v4_remote_config";
    private static final String KEY_LEGACY_SYNC = "legacy_sync_enabled";
    private static final String KEY_APPLIED = "legacy_sync_applied";

    /** v3 unique work names — exact strings from AutoUploadApplication (do not rename). */
    static final String[] LEGACY_PERIODIC_WORKS = {
        "periodic_data_sync_check",
        "periodic_sponsorships_sync",
    };
    static final String LEGACY_IMMEDIATE_WORK = "FullSyncWork";

    private RemoteConfigManagerV4() {}

    // ---------------------------------------------------------------
    // Cache
    // ---------------------------------------------------------------

    /** Safe default = true (dual-run) until first successful fetch. */
    public static boolean isLegacySyncEnabled(Context context) {
        return prefs(context).getBoolean(KEY_LEGACY_SYNC, true);
    }

    public static void cacheLegacySyncEnabled(Context context, boolean enabled) {
        prefs(context).edit().putBoolean(KEY_LEGACY_SYNC, enabled).apply();
    }

    // ---------------------------------------------------------------
    // Apply — cancel or re-enable v3 scheduling (no code deletion)
    // ---------------------------------------------------------------

    public static void applyLegacySyncFlag(Context context, boolean enabled) {
        SharedPreferences p = prefs(context);
        boolean already = p.getBoolean(KEY_APPLIED, false);
        boolean lastKnown = p.getBoolean(KEY_LEGACY_SYNC, true);
        if (already && lastKnown == enabled) {
            return; // no change — skip WorkManager churn
        }

        WorkManager wm = WorkManager.getInstance(context);
        if (!enabled) {
            for (String name : LEGACY_PERIODIC_WORKS) {
                wm.cancelUniqueWork(name);
            }
            wm.cancelUniqueWork(LEGACY_IMMEDIATE_WORK);
            Log.i(TAG, "legacy_sync_enabled=false — v3 unique works cancelled (runtime only)");
        } else {
            PeriodicWorkRequest dataCheck = new PeriodicWorkRequest.Builder(
                org.alhayah.sponsorships.DataSyncWorker.class, 15, TimeUnit.MINUTES)
                .addTag("periodic_data_sync_check")
                .build();
            wm.enqueueUniquePeriodicWork(
                "periodic_data_sync_check", ExistingPeriodicWorkPolicy.REPLACE, dataCheck);

            PeriodicWorkRequest sponsorships = new PeriodicWorkRequest.Builder(
                com.aso.app.SponsorshipSyncWorker.class, 15, TimeUnit.MINUTES)
                .addTag("periodic_sponsorships_sync")
                .build();
            wm.enqueueUniquePeriodicWork(
                "periodic_sponsorships_sync", ExistingPeriodicWorkPolicy.REPLACE, sponsorships);

            androidx.work.OneTimeWorkRequest fullSync =
                new androidx.work.OneTimeWorkRequest.Builder(com.aso.app.SponsorshipSyncWorker.class)
                    .build();
            wm.enqueueUniqueWork(LEGACY_IMMEDIATE_WORK, ExistingWorkPolicy.REPLACE, fullSync);
            Log.i(TAG, "legacy_sync_enabled=true — v3 unique works re-scheduled (rollback path)");
        }

        p.edit().putBoolean(KEY_APPLIED, true).putBoolean(KEY_LEGACY_SYNC, enabled).apply();
    }

    /** Apply cached flag on cold start (before any network). */
    public static void applyCachedFlag(Context context) {
        try {
            applyLegacySyncFlag(context, isLegacySyncEnabled(context));
        } catch (Throwable t) {
            Log.w(TAG, "applyCachedFlag failed (v3 unaffected): " + t.getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Fetch — called once per v4 sync cycle (never throws)
    // ---------------------------------------------------------------

    /**
     * GET /api/mobile/v4/config/flags → cache + apply.
     * Network/HTTP failure is non-fatal: last cached flag stays in effect.
     */
    public static void fetchAndApply(Context context, String baseUrl, String token, String deviceId) {
        try {
            UnifiedSyncOrchestratorV4.HttpResponse resp = UnifiedSyncOrchestratorV4.httpGet(
                baseUrl + "/api/mobile/v4/config/flags", token, deviceId);
            if (resp.code != 200) {
                Log.w(TAG, "flags HTTP " + resp.code + " — keeping cached flag");
                return;
            }
            org.json.JSONObject json = new org.json.JSONObject(resp.body);
            org.json.JSONObject flags = json.optJSONObject("flags");
            if (flags == null) return;

            boolean enabled = flags.optBoolean("legacy_sync_enabled", true);
            // Apply FIRST: applyLegacySyncFlag writes KEY_LEGACY_SYNC itself.
            // Caching before apply made lastKnown==enabled and hit the early-return,
            // so a true→false transition never cancelled v3 unique works.
            applyLegacySyncFlag(context, enabled);
            cacheLegacySyncEnabled(context, enabled);
            Log.i(TAG, "flags fetched: legacy_sync_enabled=" + enabled);
        } catch (Throwable t) {
            Log.w(TAG, "flags fetch failed (non-fatal): " + t.getMessage());
        }
    }

    private static SharedPreferences prefs(Context context) {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }
}
