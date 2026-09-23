package com.aso.app.v4.sync;

import android.content.Context;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

/**
 * WorkManager entry point for the unified v4 sync engine.
 *
 * Unique work name "unified_sync_v4" + ExistingPeriodicWorkPolicy.KEEP
 * guarantees at most ONE sync worker is ever active — the structural fix
 * for the dual-engine duplication problem (C2/R5).
 *
 * On transient network failure → Result.retry() with exponential backoff.
 * Outbox rows are never deleted on failure (idempotency makes retry safe).
 */
public class SyncWorkerV4 extends Worker {
    private static final String TAG = "SyncWorkerV4";
    private static final int MAX_RUN_ATTEMPTS = 5;

    public SyncWorkerV4(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.i(TAG, "doWork attempt=" + getRunAttemptCount()
            + " pending=" + SyncOutboxManagerV4.pendingCount(getApplicationContext()));

        // Skip while v3 DownloadForegroundService holds the network (Dual-Run safety).
        try {
            if (org.alhayah.sponsorships.DownloadForegroundService.isDownloading()) {
                Log.i(TAG, "v3 download running — deferring v4 sync");
                return Result.retry();
            }
        } catch (Throwable ignored) {
            // Class may be absent in a pure-v4 build.
        }

        String error = UnifiedSyncOrchestratorV4.runFullCycle(getApplicationContext());

        if (error == null) {
            return Result.success();
        }

        // Auth problems need re-login — don't spin.
        if ("unauthorized".equals(error) || "no_auth_token".equals(error)) {
            Log.w(TAG, "auth issue: " + error);
            return Result.failure();
        }

        if (getRunAttemptCount() < MAX_RUN_ATTEMPTS) {
            Log.w(TAG, "retrying after error: " + error);
            return Result.retry();
        }
        Log.e(TAG, "giving up after " + MAX_RUN_ATTEMPTS + " attempts: " + error);
        return Result.failure();
    }
}
