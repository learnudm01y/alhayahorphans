package com.aso.app;

import android.content.Context;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

/**
 * Worker lightweight function is to reset failed files right before ChunkedUploadWorker starts.
 * This is used in the MasterSync sequence when the network reconnects.
 */
public class PrepareUploadsWorker extends Worker {
    private static final String TAG = "PrepareUploadsWorker";
    private final UploadDatabaseHelper dbHelper;

    public PrepareUploadsWorker(@NonNull Context context, @NonNull WorkerParameters workerParams) {
        super(context, workerParams);
        dbHelper = UploadDatabaseHelper.getInstance(context);
    }

    @NonNull
    @Override
    public Result doWork() {
        Log.d(TAG, "⚙️ Preparing uploads: resetting failed files to pending.");
        try {
            int resetCount = dbHelper.resetFailedFiles();
            if (resetCount > 0) {
                Log.d(TAG, "🔄 Resets " + resetCount + " failed files for retry.");
            } else {
                Log.d(TAG, "ℹ️ No failed files needed resetting.");
            }
            return Result.success();
        } catch (Exception e) {
            Log.e(TAG, "❌ Failed to reset files: " + e.getMessage(), e);
            // Even if it fails, we return success so the ChunkedUploadWorker can still run on other pending files.
            return Result.success();
        }
    }
}
