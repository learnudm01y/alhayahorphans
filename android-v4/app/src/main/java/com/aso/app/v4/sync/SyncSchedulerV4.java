package com.aso.app.v4.sync;

import android.content.Context;
import android.util.Log;

import androidx.work.Constraints;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;

import java.util.concurrent.TimeUnit;

/**
 * Schedules the unified v4 sync engine.
 *
 * KEY INVARIANT:
 *   Unique work name = "unified_sync_v4"
 *   ExistingPeriodicWorkPolicy.KEEP
 * → WorkManager will NEVER spawn a second concurrent v4 sync worker.
 * This is the OS-level structural fix for parallel-sync duplication.
 *
 * Dual-Run: this does NOT cancel any v3 work names
 * ("periodic_sponsorships_sync", "periodic_data_sync_check", ...).
 * Those keep running until legacy_sync_enabled=false at cutover (Phase 7).
 */
public final class SyncSchedulerV4 {
    private static final String TAG = "SyncSchedulerV4";

    /** Unique periodic work name — do not rename. */
    public static final String UNIQUE_PERIODIC_WORK = "unified_sync_v4";
    /** Unique one-shot work name for immediate drains. */
    public static final String UNIQUE_IMMEDIATE_WORK = "unified_sync_v4_immediate";

    private static final long PERIOD_MINUTES = 15;

    private SyncSchedulerV4() {}

    private static Constraints connected() {
        return new Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build();
    }

    /** Call once from Application.onCreate — schedules the 15-minute heartbeat. */
    public static void schedulePeriodicSync(Context context) {
        PeriodicWorkRequest request = new PeriodicWorkRequest.Builder(
            SyncWorkerV4.class, PERIOD_MINUTES, TimeUnit.MINUTES)
            .setConstraints(connected())
            .setBackoffCriteria(
                androidx.work.BackoffPolicy.EXPONENTIAL,
                PeriodicWorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS)
            .addTag("unified_sync_v4")
            .build();

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            UNIQUE_PERIODIC_WORK,
            ExistingPeriodicWorkPolicy.KEEP, // never create a duplicate
            request
        );
        Log.i(TAG, "periodic unified sync scheduled (every " + PERIOD_MINUTES + "m, KEEP)");
    }

    /** Call after enqueueing outbox rows — drains soon without waiting 15 min. */
    public static void scheduleImmediateSync(Context context) {
        OneTimeWorkRequest request = new OneTimeWorkRequest.Builder(SyncWorkerV4.class)
            .setConstraints(connected())
            .setBackoffCriteria(
                androidx.work.BackoffPolicy.EXPONENTIAL,
                OneTimeWorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS)
            .addTag("unified_sync_v4")
            .build();

        // KEEP: if an immediate drain is already queued/running, do not stack another.
        WorkManager.getInstance(context).enqueueUniqueWork(
            UNIQUE_IMMEDIATE_WORK,
            ExistingWorkPolicy.KEEP,
            request
        );
    }

    /** Diagnostics / settings screen. */
    public static int pendingOutboxCount(Context context) {
        return SyncOutboxManagerV4.pendingCount(context);
    }

    /** Cancel v4 scheduling only (NOT used in Dual-Run; reserved for emergency). */
    public static void cancelAll(Context context) {
        WorkManager wm = WorkManager.getInstance(context);
        wm.cancelUniqueWork(UNIQUE_PERIODIC_WORK);
        wm.cancelUniqueWork(UNIQUE_IMMEDIATE_WORK);
        Log.w(TAG, "v4 sync scheduling cancelled");
    }
}
