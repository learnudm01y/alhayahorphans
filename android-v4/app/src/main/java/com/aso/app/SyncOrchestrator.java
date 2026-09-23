package com.aso.app;

import android.content.Context;
import androidx.work.Constraints;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;

public class SyncOrchestrator {
    private static final String SYNC_WORK_NAME = "ASO_SYNC_WORK";

    public static void scheduleSync(Context context) {
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        OneTimeWorkRequest syncRequest = new OneTimeWorkRequest.Builder(SponsorshipSyncWorker.class)
                .setConstraints(constraints)
                .setBackoffCriteria(
                        androidx.work.BackoffPolicy.EXPONENTIAL,
                        OneTimeWorkRequest.MIN_BACKOFF_MILLIS,
                        TimeUnit.MILLISECONDS)
                .build();

        WorkManager.getInstance(context)
                .enqueueUniqueWork(
                        SYNC_WORK_NAME,
                        ExistingWorkPolicy.REPLACE,
                        syncRequest);
    }

    public static void scheduleUpload(Context context) {
        UploadTaskScheduler.getInstance(context).scheduleUploadTask();
    }

    public static void scheduleFileUpload(Context context, String filePath, String fileName, long sponsorshipId) {
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        androidx.work.Data inputData = new androidx.work.Data.Builder()
                .putString("filePath", filePath)
                .putString("fileName", fileName)
                .putLong("sponsorshipId", sponsorshipId)
                .build();

        OneTimeWorkRequest uploadRequest = new OneTimeWorkRequest.Builder(ChunkedUploadWorker.class)
                .setConstraints(constraints)
                .setInputData(inputData)
                .setBackoffCriteria(
                        androidx.work.BackoffPolicy.EXPONENTIAL,
                        OneTimeWorkRequest.MIN_BACKOFF_MILLIS,
                        TimeUnit.MILLISECONDS)
                .build();

        WorkManager.getInstance(context)
                .enqueue(uploadRequest);
    }

    public static void scheduleMasterSyncOnReconnect(Context context) {
        Constraints constraints = new Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build();

        // 1. Start DataSyncForegroundService for offline data upload (non-blocking)
        org.alhayah.sponsorships.DataSyncForegroundService.startSync(context);

        // 2. Verify Drive Status Second (clear inbox)
        OneTimeWorkRequest verifyStatusRequest = new OneTimeWorkRequest.Builder(DriveStatusWorker.class)
                .setConstraints(constraints)
                .build();

        // 3. Download Server Updates Third (Sponsorship details)
        OneTimeWorkRequest downloadUpdatesRequest = new OneTimeWorkRequest.Builder(SponsorshipSyncWorker.class)
                .setConstraints(constraints)
                .build();

        // 4. Prepare Uploads Fourth (Reset failed files)
        OneTimeWorkRequest prepareUploadsRequest = new OneTimeWorkRequest.Builder(PrepareUploadsWorker.class)
                .setConstraints(constraints)
                .build();

        // 5. Chunked Upload Last (for remaining pending/failed files)
        OneTimeWorkRequest chunkedUploadRequest = new OneTimeWorkRequest.Builder(ChunkedUploadWorker.class)
                .setConstraints(constraints)
                .build();

        // Chain steps 2-5 sequentially (step 1 runs independently as Foreground Service)
        WorkManager.getInstance(context)
                .beginUniqueWork(
                        "MasterSyncOnReconnect",
                        ExistingWorkPolicy.REPLACE,
                        verifyStatusRequest)
                .then(downloadUpdatesRequest)
                .then(prepareUploadsRequest)
                .then(chunkedUploadRequest)
                .enqueue();
    }
}
