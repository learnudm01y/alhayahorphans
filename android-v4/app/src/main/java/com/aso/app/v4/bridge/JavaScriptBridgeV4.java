package com.aso.app.v4.bridge;

import android.util.Log;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import com.aso.app.v4.admin_offline.AdminOfflineDataStoreV4;
import com.aso.app.v4.admin_offline.FileManagementOfflineQueueV4;
import com.aso.app.v4.admin_offline.PermissionsCacheManagerV4;
import com.aso.app.v4.admin_offline.ReportsEngineV4;
import com.aso.app.v4.sync.SyncOutboxManagerV4;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * Capacitor bridge exposing admin_offline_v4.db + sync outbox state
 * to the mobile-app-v4 web screens. Registered in MainActivity as
 * "JavaScriptBridgeV4".
 *
 * Screens call: window.Capacitor.Plugins.JavaScriptBridgeV4.*
 */
@CapacitorPlugin(name = "JavaScriptBridgeV4")
public class JavaScriptBridgeV4 extends Plugin {
    private static final String TAG = "JSBridgeV4";

    @PluginMethod
    public void getDashboardSnapshot(PluginCall call) {
        JSONObject snap = AdminOfflineDataStoreV4.getDashboardSnapshot(getContext());
        JSObject ret = new JSObject();
        if (snap != null) {
            try {
                ret.put("success", true);
                ret.put("snapshot", new JSObject(snap.toString()));
            } catch (JSONException e) {
                call.reject("dashboard parse failed: " + e.getMessage());
                return;
            }
        } else {
            ret.put("success", false);
            ret.put("message", "no_snapshot");
        }
        call.resolve(ret);
    }

    @PluginMethod
    public void queryReportRows(PluginCall call) {
        String type = call.getString("type", "all");
        String search = call.getString("search", "");
        try {
            JSArray rows;
            if ("all".equals(type)) {
                rows = new JSArray();
                for (String t : new String[]{"sponsorships", "files", "bank_accounts"}) {
                    JSONArray part = AdminOfflineDataStoreV4.queryReportRows(getContext(), t, search);
                    for (int i = 0; i < part.length(); i++) rows.put(part.get(i));
                }
            } else {
                rows = new JSArray(AdminOfflineDataStoreV4.queryReportRows(getContext(), type, search).toString());
            }
            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("rows", rows);
            ret.put("count", rows.length());
            ret.put("fetched_at", AdminOfflineDataStoreV4.getReportsFetchedAt(
                getContext(), "all".equals(type) ? "sponsorships" : type));
            call.resolve(ret);
        } catch (Exception e) {
            call.reject("query failed: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getReportBundle(PluginCall call) {
        String type = call.getString("type", "sponsorships");
        JSONObject bundle = ReportsEngineV4.buildReportBundle(getContext(), type);
        JSObject ret = new JSObject();
        try {
            ret.put("success", true);
            ret.put("bundle", new JSObject(bundle.toString()));
        } catch (JSONException e) {
            call.reject("bundle parse failed: " + e.getMessage());
            return;
        }
        call.resolve(ret);
    }

    @PluginMethod
    public void generateReportPdf(PluginCall call) {
        String type = call.getString("type", "sponsorships");
        String title = call.getString("title", type);
        String path = ReportsEngineV4.generatePdf(getContext(), type, title);
        JSObject ret = new JSObject();
        if (path != null) {
            ret.put("success", true);
            ret.put("path", path);
        } else {
            ret.put("success", false);
            ret.put("message", "pdf_generation_failed");
        }
        call.resolve(ret);
    }

    @PluginMethod
    public void getPermissionsManifest(PluginCall call) {
        int userId = call.getInt("user_id", -1);
        JSONObject m = PermissionsCacheManagerV4.getManifest(getContext(), userId);
        JSObject ret = new JSObject();
        if (m != null) {
            try {
                ret.put("success", true);
                ret.put("manifest", new JSObject(m.toString()));
                ret.put("expired", PermissionsCacheManagerV4.isExpired(getContext(), userId));
                ret.put("near_expiry", PermissionsCacheManagerV4.isNearExpiry(getContext(), userId));
            } catch (JSONException e) {
                call.reject("manifest parse failed: " + e.getMessage());
                return;
            }
        } else {
            ret.put("success", false);
            ret.put("message", "no_manifest");
        }
        call.resolve(ret);
    }

    @PluginMethod
    public void hasPermission(PluginCall call) {
        int userId = call.getInt("user_id", -1);
        String perm = call.getString("permission", "");
        boolean sensitive = call.getBoolean("sensitive", false);
        boolean ok = sensitive
            ? PermissionsCacheManagerV4.canPerformSensitive(getContext(), userId, perm)
            : PermissionsCacheManagerV4.hasPermission(getContext(), userId, perm);
        JSObject ret = new JSObject();
        ret.put("success", true);
        ret.put("granted", ok);
        call.resolve(ret);
    }

    @PluginMethod
    public void queryFileIndex(PluginCall call) {
        String search = call.getString("search", "");
        try {
            JSONArray rows = AdminOfflineDataStoreV4.queryFileIndex(getContext(), search);
            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("rows", new JSArray(rows.toString()));
            ret.put("total", AdminOfflineDataStoreV4.countFileIndex(getContext()));
            call.resolve(ret);
        } catch (Exception e) {
            call.reject("file index query failed: " + e.getMessage());
        }
    }

    @PluginMethod
    public void queueFileDelete(PluginCall call) {
        String uuid = FileManagementOfflineQueueV4.enqueueDelete(getContext(),
            call.getString("file_index_uuid"),
            call.getString("file_id"),
            call.getString("stored_file_name"),
            call.getString("identity_number"));
        resolveQueued(call, uuid);
    }

    @PluginMethod
    public void queueFileRename(PluginCall call) {
        String uuid = FileManagementOfflineQueueV4.enqueueRename(getContext(),
            call.getString("file_index_uuid"),
            call.getString("file_id"),
            call.getString("new_file_name"));
        resolveQueued(call, uuid);
    }

    @PluginMethod
    public void queueFileMove(PluginCall call) {
        String uuid = FileManagementOfflineQueueV4.enqueueMove(getContext(),
            call.getString("file_index_uuid"),
            call.getString("file_id"),
            call.getString("destination"));
        resolveQueued(call, uuid);
    }

    @PluginMethod
    public void getSyncHealth(PluginCall call) {
        JSObject ret = new JSObject();
        ret.put("success", true);
        ret.put("pending_outbox", SyncOutboxManagerV4.pendingCount(getContext()));
        ret.put("pending_file_ops", FileManagementOfflineQueueV4.pendingCount(getContext()));
        ret.put("last_admin_pull",
            AdminOfflineDataStoreV4.getLastAdminPull(getContext()));
        ret.put("device_id",
            com.aso.app.v4.sync.DeviceIdentityManagerV4.getDeviceId(getContext()));
        ret.put("server_registered",
            com.aso.app.v4.sync.DeviceIdentityManagerV4.isRegisteredOnServer(getContext()));
        call.resolve(ret);
    }

    private void resolveQueued(PluginCall call, String uuid) {
        JSObject ret = new JSObject();
        if (uuid != null) {
            ret.put("success", true);
            ret.put("client_uuid", uuid);
            ret.put("pending", SyncOutboxManagerV4.pendingCount(getContext()));
        } else {
            ret.put("success", false);
            ret.put("message", "enqueue_failed");
        }
        call.resolve(ret);
    }
}
