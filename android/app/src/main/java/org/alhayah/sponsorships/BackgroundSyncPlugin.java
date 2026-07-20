package org.alhayah.sponsorships;

import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.util.Log;
import com.getcapacitor.JSObject;
import com.getcapacitor.JSArray;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import org.json.JSONArray;
import org.json.JSONObject;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;

@CapacitorPlugin(name = "BackgroundSync")
public class BackgroundSyncPlugin extends Plugin {
    private static final String TAG = "BackgroundSyncPlugin";
    private static BackgroundSyncPlugin instance;

    private static int lastProgress = -1;
    private static String lastMessage = "";
    private static int lastTotal = 0;

    @Override
    public void load() {
        instance = this;
    }

    public static void emitProgress(int progress, String message, int total) {
        lastProgress = progress;
        lastMessage = message;
        lastTotal = total;
        
        if (instance != null) {
            JSObject ret = new JSObject();
            ret.put("progress", progress);
            ret.put("message", message);
            ret.put("total", total);
            instance.notifyListeners("syncProgress", ret);
        }
    }

    public static void emitFinished() {
        lastProgress = 100;
        lastMessage = "مكتمل";
        if (instance != null) {
            JSObject ret = new JSObject();
            ret.put("success", true);
            instance.notifyListeners("syncFinished", ret);
        }
    }

    @PluginMethod
    public void getSyncState(PluginCall call) {
        JSObject ret = new JSObject();
        ret.put("progress", lastProgress);
        ret.put("message", lastMessage);
        ret.put("total", lastTotal);
        ret.put("isSyncing", lastProgress > -1 && lastProgress < 100);
        call.resolve(ret);
    }

    @PluginMethod
    public void saveLookup(PluginCall call) {
        String type = call.getString("type");
        JSArray dataArray = call.getArray("data");
        
        if (type == null || dataArray == null) {
            call.reject("Must provide type and data");
            return;
        }
        
        try {
            com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(getContext());
            dbHelper.saveLookup(type, dataArray.toString());
            
            JSObject ret = new JSObject();
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to save lookup natively", e);
            call.reject("Failed to save lookup", e);
        }
    }

    @PluginMethod
    public void startSync(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncForegroundService.startSync(context);
            JSObject ret = new JSObject();
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to start sync", e);
            call.reject("Failed to start sync: " + e.getMessage());
        }
    }

    private android.content.BroadcastReceiver realTimeReceiver = new android.content.BroadcastReceiver() {
        @Override
        public void onReceive(Context context, android.content.Intent intent) {
            if ("com.aso.app.REALTIME_UPDATE".equals(intent.getAction())) {
                JSObject ret = new JSObject();
                ret.put("sponsorship_id", intent.getIntExtra("sponsorship_id", -1));
                
                // 🆕 تمرير نوع التحديث (lookup_update أو sponsorship) للـ JS
                String updateType = intent.getStringExtra("update_type");
                if (updateType != null) {
                    ret.put("update_type", updateType);
                }
                String lookupType = intent.getStringExtra("lookup_type");
                if (lookupType != null) {
                    ret.put("lookup_type", lookupType);
                }
                
                String data = intent.getStringExtra("sponsorship_data");
                if (data != null) {
                    try {
                        ret.put("sponsorship_data", new org.json.JSONObject(data));
                    } catch (org.json.JSONException e) {
                        ret.put("sponsorship_data", data);
                    }
                }
                notifyListeners("realtimeUpdate", ret);
            } else if ("com.aso.app.WEBSOCKET_RECONNECTED".equals(intent.getAction())) {
                // إبلاغ الواجهة بعودة اتصال الـ WebSocket لسحب التحديثات الضائعة
                notifyListeners("websocketReconnected", new JSObject());
            } else if ("com.aso.app.SYNC_PROGRESS".equals(intent.getAction())) {
                int progress = intent.getIntExtra("progress", -1);
                String message = intent.getStringExtra("message");
                int total = intent.getIntExtra("total", 0);
                
                lastProgress = progress;
                lastMessage = message;
                lastTotal = total;
                
                JSObject ret = new JSObject();
                if (progress == 100) {
                    ret.put("success", true);
                    notifyListeners("syncFinished", ret);
                } else {
                    ret.put("progress", progress);
                    ret.put("message", message);
                    ret.put("total", total);
                    notifyListeners("syncProgress", ret);
                }
            }
        }
    };

    @Override
    protected void handleOnStart() {
        super.handleOnStart();
        android.content.IntentFilter filter = new android.content.IntentFilter();
        filter.addAction("com.aso.app.REALTIME_UPDATE");
        filter.addAction("com.aso.app.SYNC_PROGRESS");
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
            getContext().registerReceiver(realTimeReceiver, filter, Context.RECEIVER_NOT_EXPORTED);
        } else {
            getContext().registerReceiver(realTimeReceiver, filter);
        }
    }

    @Override
    protected void handleOnStop() {
        super.handleOnStop();
        try {
            getContext().unregisterReceiver(realTimeReceiver);
        } catch (Exception e) {
            Log.w(TAG, "Receiver already unregistered");
        }
    }

    @PluginMethod
    public void startFullDownload(PluginCall call) {
        try {
            Context context = getContext();
            org.alhayah.sponsorships.DownloadForegroundService.startDownload(context);
            
            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("message", "Full download started in resilient foreground service");
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to start full download", e);
            call.reject("Failed to start full download: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getSponsorships(PluginCall call) {
        try {
            int page = call.getInt("page", 1);
            int limit = call.getInt("limit", 20);
            int sponsorId = call.getInt("sponsorId", 0);
            int statusId = call.getInt("statusId", 0);
            String search = call.getString("search", "");
            
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            
            JSONArray data = dbHelper.getSponsorships(page, limit, sponsorId, statusId, search);
            
            JSObject ret = new JSObject();
            ret.put("data", data);
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to get sponsorships", e);
            call.reject("Failed to get sponsorships: " + e.getMessage());
        }
    }

    @PluginMethod
    public void saveSponsorship(PluginCall call) {
        try {
            int id = call.getInt("id", 0);
            JSObject data = call.getObject("data");
            
            if (id > 0 && data != null) {
                Context context = getContext();
                com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
                dbHelper.saveSponsorship(id, data.toString());
                
                JSObject ret = new JSObject();
                ret.put("success", true);
                call.resolve(ret);
            } else {
                call.reject("Invalid id or data");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to save sponsorship", e);
            call.reject("Failed to save sponsorship: " + e.getMessage());
        }
    }
    
    @PluginMethod
    public void deleteSponsorship(PluginCall call) {
        try {
            int id = call.getInt("id", 0);
            
            if (id > 0) {
                Context context = getContext();
                com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
                android.database.sqlite.SQLiteDatabase db = dbHelper.getWritableDatabase();
                db.delete("sponsorships", "id = ?", new String[]{String.valueOf(id)});
                
                JSObject ret = new JSObject();
                ret.put("success", true);
                call.resolve(ret);
            } else {
                call.reject("Invalid id");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to delete sponsorship", e);
            call.reject("Failed to delete sponsorship: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getPendingEntityIds(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncDatabaseHelper syncDbHelper = DataSyncDatabaseHelper.getInstance(context);
            java.util.Set<Integer> pendingIds = syncDbHelper.getPendingEntityIds();
            
            org.json.JSONArray jsonArray = new org.json.JSONArray();
            for (Integer id : pendingIds) {
                jsonArray.put(id);
            }
            
            JSObject ret = new JSObject();
            ret.put("success", true);
            ret.put("pendingIds", jsonArray);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to get pending entity IDs", e);
            call.reject("Failed to get pending entity IDs: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getSponsorship(PluginCall call) {
        try {
            int id = call.getInt("id", 0);
            if (id == 0) {
                call.reject("Sponsorship ID required");
                return;
            }
            
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            
            String jsonStr = dbHelper.getSponsorship(id);
            if (jsonStr != null) {
                JSObject ret = new JSObject();
                ret.put("data", new JSObject(jsonStr));
                ret.put("success", true);
                call.resolve(ret);
            } else {
                call.reject("Sponsorship not found");
            }
        } catch (Exception e) {
            Log.e(TAG, "Failed to get sponsorship", e);
            call.reject("Failed to get sponsorship: " + e.getMessage());
        }
    }

    @PluginMethod
    public void searchSponsorships(PluginCall call) {
        try {
            String query = call.getString("query", "");
            int page = call.getInt("page", 1);
            int limit = call.getInt("limit", 20);
            
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            
            JSONArray data = dbHelper.searchSponsorships(query, page, limit);
            
            JSObject ret = new JSObject();
            ret.put("data", data);
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to search sponsorships", e);
            call.reject("Failed to search sponsorships: " + e.getMessage());
        }
    }

    @PluginMethod
    public void updateSponsorshipLocal(PluginCall call) {
        try {
            int id = call.getInt("id", 0);
            JSObject data = call.getObject("data");
            
            if (id == 0 || data == null) {
                call.reject("Sponsorship ID and data required");
                return;
            }
            
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper dbHelper = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            
            String existingDataStr = dbHelper.getSponsorship(id);
            if (existingDataStr != null) {
                try {
                    org.json.JSONObject existingData = new org.json.JSONObject(existingDataStr);
                    java.util.Iterator<String> keys = data.keys();
                    while(keys.hasNext()) {
                        String key = keys.next();
                        existingData.put(key, data.get(key));
                    }
                    dbHelper.saveSponsorship(id, existingData.toString());
                } catch(Exception e) {
                    dbHelper.saveSponsorship(id, data.toString());
                }
            } else {
                dbHelper.saveSponsorship(id, data.toString());
            }
            
            JSObject ret = new JSObject();
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to update sponsorship locally", e);
            call.reject("Failed to update sponsorship: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getSyncStatus(PluginCall call) {
        try {
            Context context = getContext();
            DataSyncDatabaseHelper dbHelper = DataSyncDatabaseHelper.getInstance(context);
            JSObject ret = new JSObject();
            ret.put("pending", dbHelper.getPendingDataCount());
            ret.put("uploaded", dbHelper.getUploadedDataCount());
            ret.put("failed", dbHelper.getFailedDataCount());
            ret.put("isSyncing", false);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to get sync status", e);
            call.reject("Failed to get sync status: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getAppStatistics(PluginCall call) {
        try {
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper sponsorshipsDb = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            DataSyncDatabaseHelper syncDbHelper = DataSyncDatabaseHelper.getInstance(context);
            com.aso.app.UploadDatabaseHelper uploadDbHelper = com.aso.app.UploadDatabaseHelper.getInstance(context);

            JSObject ret = new JSObject();
            ret.put("sponsors", 0); // Deprecated: Managed entirely by JS IndexedDB now
            ret.put("localSponsorships", sponsorshipsDb.getCount());
            ret.put("pendingChanges", syncDbHelper.getPendingDataCount());
            ret.put("pendingUploads", uploadDbHelper.getPendingFilesCount());

            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to get app statistics from SQLite", e);
            call.reject("Failed to get app statistics: " + e.getMessage());
        }
    }

    @PluginMethod
    public void getLookup(PluginCall call) {
        try {
            String type = call.getString("type");
            if (type == null || type.isEmpty()) {
                call.reject("Type is required");
                return;
            }
            Context context = getContext();
            com.aso.app.SponsorshipsDatabaseHelper db = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
            String jsonPayload = db.getLookup(type);
            
            JSObject ret = new JSObject();
            if (jsonPayload != null) {
                ret.put("data", new org.json.JSONArray(jsonPayload));
            } else {
                ret.put("data", new org.json.JSONArray());
            }
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to get lookup", e);
            call.reject("Failed to get lookup: " + e.getMessage());
        }
    }

    @PluginMethod
    public void queueDataSync(PluginCall call) {
        try {
            String dataType = call.getString("dataType", "sponsorship_update");
            JSObject data = call.getObject("data");
            String endpoint = call.getString("endpoint", "/mobile/sync/update");

            if (data == null) {
                call.reject("Data object is required");
                return;
            }

            Context context = getContext();
            DataSyncDatabaseHelper syncDbHelper = DataSyncDatabaseHelper.getInstance(context);
            long id = syncDbHelper.addDataToQueue(dataType, data.toString(), endpoint);

            if (id > 0) {
                // Also update local copy if it's a sponsorship update
                if ("sponsorship_update".equals(dataType)) {
                    int sponsorshipId = data.getInteger("id", 0);
                    if (sponsorshipId > 0) {
                        com.aso.app.SponsorshipsDatabaseHelper sponsorshipsDb = com.aso.app.SponsorshipsDatabaseHelper.getInstance(context);
                        synchronized (com.aso.app.SponsorshipsDatabaseHelper.class) {
                            String existingDataStr = sponsorshipsDb.getSponsorship(sponsorshipId);
                            if (existingDataStr != null) {
                                try {
                                    org.json.JSONObject existingData = new org.json.JSONObject(existingDataStr);
                                    java.util.Iterator<String> keys = data.keys();
                                    while(keys.hasNext()) {
                                        String key = keys.next();
                                        existingData.put(key, data.get(key));
                                    }
                                    sponsorshipsDb.saveSponsorship(sponsorshipId, existingData.toString());
                                    
                                    // Fire REALTIME_UPDATE so the UI refreshes IMMEDIATELY!
                                    android.content.Intent updateIntent = new android.content.Intent("com.aso.app.REALTIME_UPDATE");
                                    updateIntent.putExtra("sponsorship_id", sponsorshipId);
                                    context.sendBroadcast(updateIntent);
                                } catch(Exception e) {
                                    Log.e(TAG, "Failed to merge local data", e);
                                    sponsorshipsDb.saveSponsorship(sponsorshipId, data.toString());
                                }
                            } else {
                                sponsorshipsDb.saveSponsorship(sponsorshipId, data.toString());
                            }
                        }
                    }
                }

                // Trigger Background worker
                DataSyncForegroundService.startSync(context);

                JSObject ret = new JSObject();
                ret.put("success", true);
                ret.put("id", id);
                call.resolve(ret);
            } else {
                call.reject("Failed to queue data for sync");
            }

        } catch (Exception e) {
            Log.e(TAG, "Failed to queue data sync", e);
            call.reject("Failed to queue data sync: " + e.getMessage());
        }
    }

    @PluginMethod
    public void isLoggedIn(PluginCall call) {
        try {
            Context context = getContext();
            SharedPreferences prefs = context.getSharedPreferences("auth_prefs", Context.MODE_PRIVATE);
            String token = prefs.getString("api_token", "");
            JSObject ret = new JSObject();
            ret.put("isLoggedIn", !token.isEmpty());
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "Failed to check login status", e);
            JSObject ret = new JSObject();
            ret.put("isLoggedIn", false);
            call.resolve(ret);
        }
    }

    @PluginMethod
    public void setApiUrl(PluginCall call) {
        try {
            String url = call.getString("url");
            if (url == null || url.isEmpty()) {
                call.reject("URL must be provided");
                return;
            }
            Context context = getContext();
            com.aso.app.ApiConfig.setBaseUrl(context, url);
            JSObject ret = new JSObject();
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            call.reject("Failed to set API URL: " + e.getMessage());
        }
    }

    @PluginMethod
    public void setAuthToken(PluginCall call) {
        try {
            String token = call.getString("token");
            if (token == null) token = "";
            Context context = getContext();
            context.getSharedPreferences("auth_prefs", Context.MODE_PRIVATE)
                   .edit()
                   .putString("api_token", token)
                   .apply();
            JSObject ret = new JSObject();
            ret.put("success", true);
            call.resolve(ret);
        } catch (Exception e) {
            call.reject("Failed to set auth token: " + e.getMessage());
        }
    }
}
