package com.aso.app;

import android.util.Log;
import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.HashMap;
import java.util.Map;

/**
 * Bridge للحصول على بيانات من IndexedDB (أسماء الجمعيات والمكفولين)
 * JavaScript يستدعي saveSponsorshipMappings() مرة واحدة لتخزين جميع البيانات في SQLite
 */
@CapacitorPlugin(name = "IndexedDBBridge")
public class IndexedDBBridge extends Plugin {
    private static final String TAG = "IndexedDBBridge";

    private static IndexedDBBridge instance;

    public static IndexedDBBridge getInstance() {
        return instance;
    }

    @Override
    public void load() {
        super.load();
        instance = this;
        Log.d(TAG, "✅ IndexedDBBridge plugin loaded");
    }

    /**
     * JavaScript يستدعي هذا لحفظ جميع mappings دفعة واحدة
     * مثال من JavaScript:
     * await IndexedDBBridge.saveSponsorshipMappings({
     *   mappings: [
     *     {sponsorshipId: 908, associationName: "جمعية الحياة", personName: "أحمد محمد"},
     *     {sponsorshipId: 909, associationName: "جمعية الأمل", personName: "فاطمة علي"}
     *   ]
     * });
     */
    @PluginMethod
    public void saveSponsorshipMappings(PluginCall call) {
        try {
            JSArray mappingsArray = call.getArray("mappings");
            if (mappingsArray == null) {
                call.reject("Missing 'mappings' parameter");
                return;
            }

            SponsorshipMappingHelper mappingHelper = SponsorshipMappingHelper.getInstance(getContext());
            Map<Integer, SponsorshipMappingHelper.MappingData> mappings = new HashMap<>();

            for (int i = 0; i < mappingsArray.length(); i++) {
                JSONObject obj = mappingsArray.getJSONObject(i);
                int sponsorshipId = obj.getInt("sponsorshipId");
                String associationName = obj.optString("associationName", "General");
                String personName = obj.optString("personName", "unknown");

                mappings.put(sponsorshipId, new SponsorshipMappingHelper.MappingData(associationName, personName));
            }

            mappingHelper.saveBatchMappings(mappings);

            Log.d(TAG, "✅ Saved " + mappings.size() + " sponsorship mappings to SQLite");

            JSObject result = new JSObject();
            result.put("success", true);
            result.put("count", mappings.size());
            call.resolve(result);

        } catch (JSONException e) {
            Log.e(TAG, "❌ JSON parsing error: " + e.getMessage(), e);
            call.reject("JSON parsing error: " + e.getMessage());
        } catch (Exception e) {
            Log.e(TAG, "❌ Error saving mappings: " + e.getMessage(), e);
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * حفظ mapping لكفالة واحدة
     */
    @PluginMethod
    public void saveSingleMapping(PluginCall call) {
        try {
            Integer sponsorshipId = call.getInt("sponsorshipId");
            String associationName = call.getString("associationName", "General");
            String personName = call.getString("personName", "unknown");

            if (sponsorshipId == null) {
                call.reject("Missing sponsorshipId");
                return;
            }

            SponsorshipMappingHelper mappingHelper = SponsorshipMappingHelper.getInstance(getContext());
            mappingHelper.saveMapping(sponsorshipId, associationName, personName);

            JSObject result = new JSObject();
            result.put("success", true);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Error saving single mapping: " + e.getMessage(), e);
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * الحصول على عدد mappings المحفوظة
     */
    @PluginMethod
    public void getMappingCount(PluginCall call) {
        try {
            SponsorshipMappingHelper mappingHelper = SponsorshipMappingHelper.getInstance(getContext());
            int count = mappingHelper.getCount();

            JSObject result = new JSObject();
            result.put("count", count);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Error getting count: " + e.getMessage(), e);
            call.reject("Error: " + e.getMessage());
        }
    }

    /**
     * مسح جميع mappings
     */
    @PluginMethod
    public void clearMappings(PluginCall call) {
        try {
            SponsorshipMappingHelper mappingHelper = SponsorshipMappingHelper.getInstance(getContext());
            mappingHelper.clearAll();

            Log.d(TAG, "🗑️ All mappings cleared");

            JSObject result = new JSObject();
            result.put("success", true);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "❌ Error clearing mappings: " + e.getMessage(), e);
            call.reject("Error: " + e.getMessage());
        }
    }
}
