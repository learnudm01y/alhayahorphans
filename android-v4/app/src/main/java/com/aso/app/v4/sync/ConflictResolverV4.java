package com.aso.app.v4.sync;

import android.content.Context;
import android.util.Log;

import com.aso.app.v4.db.SyncDatabaseHelperV4;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * Handles conflicts reported by the server (conflict=true / needs_review=true).
 *
 * Policy (matches backend ConflictDetectionServiceV4):
 *  - NEVER auto-merge. Sensitive fields (bank accounts, death status,
 *    sponsorship status) always require human review.
 *  - Locally mark the record as conflict and surface it in the UI queue.
 *  - Server remains the source of truth for the review decision.
 */
public final class ConflictResolverV4 {
    private static final String TAG = "ConflictResolverV4";

    private ConflictResolverV4() {}

    /** Called after a successful server response for one outbox item. */
    public static void absorbServerResult(Context context, String clientUuid, String entityType,
                                          JSONObject result) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);
        boolean conflict = result.optBoolean("conflict", false);
        Integer serverId = extractServerId(result);

        // Re-read payload from outbox is unnecessary — caller passes it via upsertRecord.
        if (conflict) {
            Log.w(TAG, "Conflict flagged for " + entityType + "/" + clientUuid
                + " — queued for human review");
        }
        db.upsertRecord(clientUuid, entityType, serverId, result.toString(), conflict);
    }

    public static void absorbServerResult(Context context, String clientUuid, String entityType,
                                          String payloadJson, JSONObject result) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);
        boolean conflict = result.optBoolean("conflict", false);
        Integer serverId = extractServerId(result);
        if (conflict) {
            Log.w(TAG, "Conflict flagged for " + entityType + "/" + clientUuid);
        }
        db.upsertRecord(clientUuid, entityType, serverId, payloadJson, conflict);
    }

    private static Integer extractServerId(JSONObject result) {
        if (!result.has("id") || result.isNull("id")) {
            return null;
        }
        try {
            return result.getInt("id");
        } catch (JSONException e) {
            return null;
        }
    }

    /** Local records currently marked as needing review (for the review screen). */
    public static JSONArray listLocalConflicts(Context context) {
        return SyncDatabaseHelperV4.getInstance(context)
            .queryRecords("conflict_list", null); // placeholder — real queue lives on server
    }

    public static boolean hasLocalConflict(Context context, String clientUuid) {
        SyncDatabaseHelperV4 db = SyncDatabaseHelperV4.getInstance(context);
        // records_v4 stores needs_review flag; a targeted query would go here.
        return db.getState("conflict:" + clientUuid) != null;
    }
}
