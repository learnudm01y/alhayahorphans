package com.aso.app;

import android.app.Activity;
import android.content.Intent;
import android.util.Log;
import androidx.activity.result.ActivityResult;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "BarcodeScanner")
public class BarcodeScannerPlugin extends Plugin {

    private static final String TAG = "BarcodeScannerPlugin";

    @PluginMethod
    public void scan(PluginCall call) {
        Log.e(TAG, "scan() called from JavaScript");
        Intent intent = new Intent(getContext(), BarcodeScannerActivity.class);
        startActivityForResult(call, intent, "handleScanResult");
    }

    @ActivityCallback
    private void handleScanResult(PluginCall call, ActivityResult result) {
        if (result.getResultCode() == Activity.RESULT_OK && result.getData() != null) {
            String barcode = result.getData().getStringExtra("barcode");
            Log.e(TAG, "Barcode scanned: " + barcode);
            JSObject response = new JSObject();
            response.put("success", true);
            response.put("barcode", barcode);
            call.resolve(response);
        } else {
            call.reject("تم إلغاء المسح");
        }
    }
}
