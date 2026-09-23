package com.aso.app.v4.sync;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.db.SyncDatabaseHelperV4;

import org.json.JSONObject;

import java.util.List;

/**
 * Screens call enqueue() whenever they create/update a record offline.
 *
 * Flow:
 *  1. Ensure client_uuid exists (generate if missing).
 *  2. Compute idempotency_key = sha256(uuid|op|payload).
 *  3. Persist row in outbox_local with status=pending.
 *  4. Kick SyncSchedulerV4 so WorkManager drains the outbox when online.
 *
 * The row is only deleted after the server acknowledges it (markApplied).
 * Network failure leaves it pending — safe because the server is idempotent.
 */
public final class SyncOutboxManagerV4 {
    private static final String TAG = "OutboxV4";
    private static final int BATCH_SIZE = 50;

    private SyncOutboxManagerV4() {}

    public static String enqueue(Context context, String entityType, String operation,
                                 JSONObject payload) {
        return enqueue(context, entityType, operation, payload, null);
    }

    public static String enqueue(Context context, String entityType, String operation,
                                 JSONObject payload, String existingClientUuid) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);

        String clientUuid = existingClientUuid;
        if (clientUuid == null || clientUuid.isEmpty()) {
            clientUuid = payload.optString("client_uuid", "");
            if (clientUuid.isEmpty()) {
                clientUuid = SyncDatabaseHelperV4.newClientUuid();
            }
        }

        String payloadJson = payload.toString();
        String idempotencyKey = IdempotencyKeyGeneratorV4.generate(clientUuid, operation, payloadJson);

        db.enqueue(clientUuid, idempotencyKey, entityType, operation, payloadJson);
        Log.d(TAG, "enqueued " + entityType + " uuid=" + clientUuid);

        // Ask WorkManager to drain soon (unique — never spawns a second worker).
        SyncSchedulerV4.scheduleImmediateSync(context);
        return clientUuid;
    }

    public static int pendingCount(Context context) {
        return SyncDatabaseHelperV4.getInstance(context).getPendingCount();
    }

    public static List<SyncDatabaseHelperV4.OutboxItem> peekBatch(Context context) {
        return SyncDatabaseHelperV4.getInstance(context).getPendingBatch(BATCH_SIZE);
    }

    public static void markApplied(Context context, long outboxId) {
        SyncDatabaseHelperV4.getInstance(context).markApplied(outboxId);
    }

    public static void markFailed(Context context, long outboxId, String error) {
        SyncDatabaseHelperV4.getInstance(context).markFailed(outboxId, error);
    }
}
