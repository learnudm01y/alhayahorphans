package com.aso.app.v4.sync;

import android.content.Context;
import android.database.Cursor;
import android.util.Log;

import com.aso.app.SponsorshipsDatabaseHelper;
import com.aso.app.v4.db.SyncDatabaseHelperV4;

import org.json.JSONObject;

/**
 * One-way bridge: records_v4(sponsorships) → sponsorships_data.db.
 *
 * After v4 cutover (legacy_sync_enabled=false) the UI still reads
 * sponsorships_data.db via BackgroundSyncPlugin.getSponsorships, while
 * UnifiedSyncOrchestratorV4 only writes to sync_v4.db (Dual-Run rule).
 * Without this bridge, status edits made on the website never appear
 * on the phone.
 *
 * Safety:
 *  - skips entities with pending local uploads (data_sync.db outbox)
 *  - never overwrites a local row whose updated_at is newer than server
 *  - merges into existing json_payload so nested fields survive
 */
public final class SponsorshipMirrorBridgeV4 {
    private static final String TAG = "SponsorshipMirrorV4";

    private SponsorshipMirrorBridgeV4() {}

    /** Returns number of rows written to sponsorships_data.db. */
    public static int mirrorSponsorships(Context context) {
        SyncDatabaseHelperV4 v4 = SyncDatabaseHelperV4.getInstance(context);
        SponsorshipsDatabaseHelper ui = SponsorshipsDatabaseHelper.getInstance(context);
        java.util.Set<Integer> pendingIds =
            org.alhayah.sponsorships.DataSyncDatabaseHelper.getInstance(context)
                .getPendingEntityIds();

        int written = 0;
        int skippedPending = 0;
        int skippedStale = 0;

        Cursor c = v4.getWritableDatabase().rawQuery(
            "SELECT server_id, payload_json FROM records_v4 "
                + "WHERE entity_type = 'sponsorships' AND server_id IS NOT NULL",
            null);
        try {
            while (c.moveToNext()) {
                int id = c.getInt(0);
                if (id <= 0) continue;
                if (pendingIds != null && pendingIds.contains(id)) {
                    skippedPending++;
                    continue;
                }

                JSONObject serverRow;
                try {
                    serverRow = new JSONObject(c.getString(1));
                } catch (Exception e) {
                    continue;
                }

                long serverUpdated = parseUpdatedAt(serverRow.optString("updated_at", ""));
                JSONObject merged = mergeWithLocal(ui, id, serverRow);
                if (merged == null) continue;

                long localUpdated = merged.optLong("__local_updated_at", -1);
                if (localUpdated > 0 && serverUpdated > 0 && localUpdated > serverUpdated) {
                    skippedStale++;
                    continue;
                }

                // strip helper key before persist
                merged.remove("__local_updated_at");
                ui.saveSponsorship(id, merged.toString());
                written++;
            }
        } finally {
            c.close();
        }

        if (written > 0 || skippedPending > 0 || skippedStale > 0) {
            Log.i(TAG, "mirror done: written=" + written
                + " skippedPending=" + skippedPending
                + " skippedStale=" + skippedStale);
        }
        return written;
    }

    /**
     * Overlay server columns onto the existing local json_payload so nested
     * structures (guardian_data, bank_accounts, status_name, ...) are kept.
     * Returns null if there is nothing usable to write.
     */
    private static JSONObject mergeWithLocal(SponsorshipsDatabaseHelper ui, int id,
                                             JSONObject serverRow) {
        JSONObject local = null;
        try {
            String raw = ui.getSponsorship(id);
            if (raw != null && !raw.isEmpty()) {
                local = new JSONObject(raw);
            }
        } catch (Exception ignored) {}
        JSONObject out = (local != null) ? local : new JSONObject();

        try {
            // Core columns the list UI reads
            copyIfPresent(serverRow, out, "id");
            copyIfPresent(serverRow, out, "sponsorship_status_id");
            copyIfPresent(serverRow, out, "sponsorship_status");
            copyIfPresent(serverRow, out, "status_id");
            copyIfPresent(serverRow, out, "orphan_name");
            copyIfPresent(serverRow, out, "guardian_name");
            copyIfPresent(serverRow, out, "identity_number");
            copyIfPresent(serverRow, out, "guardian_identity_number");
            copyIfPresent(serverRow, out, "internal_file_number");
            copyIfPresent(serverRow, out, "external_file_number");
            copyIfPresent(serverRow, out, "relation_id_number");
            copyIfPresent(serverRow, out, "sponsor_id");
            copyIfPresent(serverRow, out, "person_type");
            copyIfPresent(serverRow, out, "health_status_id");
            copyIfPresent(serverRow, out, "notes");
            copyIfPresent(serverRow, out, "sponsoring_organization");
            copyIfPresent(serverRow, out, "sponsorship_start_date");
            copyIfPresent(serverRow, out, "sponsorship_end_date");
            copyIfPresent(serverRow, out, "sponsored_birth_date");
            copyIfPresent(serverRow, out, "updated_at");
            copyIfPresent(serverRow, out, "created_at");

            if (local != null) {
                out.put("__local_updated_at", local.optLong("updated_at_millis", -1));
                if (out.optLong("__local_updated_at", -1) <= 0) {
                    out.put("__local_updated_at",
                        parseUpdatedAt(local.optString("updated_at", "")));
                }
            }
            // Prefer server updated_at for the save path
            out.put("updated_at", serverRow.optString("updated_at",
                out.optString("updated_at", "")));
            return out;
        } catch (Exception e) {
            Log.w(TAG, "merge failed for id=" + id + ": " + e.getMessage());
            return null;
        }
    }

    private static void copyIfPresent(JSONObject src, JSONObject dst, String key)
            throws Exception {
        if (src.has(key) && !src.isNull(key)) {
            dst.put(key, src.get(key));
        }
    }

    private static long parseUpdatedAt(String s) {
        if (s == null || s.isEmpty()) return -1;
        try {
            java.text.SimpleDateFormat sdf;
            if (s.contains("T")) {
                sdf = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'",
                    java.util.Locale.US);
                sdf.setTimeZone(java.util.TimeZone.getTimeZone("UTC"));
            } else {
                sdf = new java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss",
                    java.util.Locale.US);
            }
            return sdf.parse(s).getTime();
        } catch (Exception e) {
            try {
                java.text.SimpleDateFormat sdf = new java.text.SimpleDateFormat(
                    "yyyy-MM-dd HH:mm:ss", java.util.Locale.US);
                return sdf.parse(s).getTime();
            } catch (Exception e2) {
                return -1;
            }
        }
    }
}
