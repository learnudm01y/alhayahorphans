package com.aso.app.v4.admin_offline;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.sync.SyncOutboxManagerV4;

import org.json.JSONObject;

/**
 * Deferred file-management operations (Doc/03 §5).
 *
 * Delete / rename / move are NEVER executed locally-and-hopefully:
 * they are enqueued into the SAME sync_outbox_v4 pipeline (idempotent),
 * shown as Optimistic UI with a "pending sync" badge, and applied on
 * the server when connectivity returns.
 *
 * entity_type = "file_management" so the server can dispatch them.
 */
public final class FileManagementOfflineQueueV4 {
    private static final String TAG = "FileMgmtQueueV4";
    public static final String ENTITY_TYPE = "file_management";

    private FileManagementOfflineQueueV4() {}

    /** @return client_uuid of the queued operation (for optimistic UI tracking). */
    public static String enqueueDelete(Context context, String fileIndexClientUuid,
                                       String fileId, String storedFileName, String identityNumber) {
        return enqueue(context, "delete", fileIndexClientUuid, fileId,
            storedFileName, identityNumber, null);
    }

    public static String enqueueRename(Context context, String fileIndexClientUuid,
                                       String fileId, String newFileName) {
        return enqueue(context, "rename", fileIndexClientUuid, fileId,
            newFileName, null, null);
    }

    public static String enqueueMove(Context context, String fileIndexClientUuid,
                                     String fileId, String destination) {
        return enqueue(context, "move", fileIndexClientUuid, fileId,
            null, null, destination);
    }

    private static String enqueue(Context context, String operation,
                                  String fileIndexClientUuid, String fileId,
                                  String nameArg, String identityNumber, String destination) {
        try {
            JSONObject payload = new JSONObject();
            payload.put("file_management_operation", operation);
            if (fileIndexClientUuid != null) {
                payload.put("file_index_uuid", fileIndexClientUuid);
            }
            if (fileId != null) payload.put("file_id", fileId);
            if (nameArg != null) {
                if ("rename".equals(operation)) {
                    payload.put("new_file_name", nameArg);
                } else if ("delete".equals(operation)) {
                    payload.put("stored_file_name", nameArg);
                }
            }
            if (identityNumber != null) payload.put("identity_number", identityNumber);
            if (destination != null) payload.put("destination", destination);
            payload.put("queued_at", System.currentTimeMillis());

            String uuid = SyncOutboxManagerV4.enqueue(context, ENTITY_TYPE,
                operation, payload, fileIndexClientUuid);
            Log.i(TAG, "queued file_" + operation + " uuid=" + uuid
                + " fileId=" + fileId);
            return uuid;
        } catch (Exception e) {
            Log.e(TAG, "enqueue failed", e);
            return null;
        }
    }

    public static int pendingCount(Context context) {
        return SyncOutboxManagerV4.pendingCount(context);
    }
}
