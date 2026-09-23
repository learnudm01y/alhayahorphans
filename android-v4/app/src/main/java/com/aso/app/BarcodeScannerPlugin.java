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
    private static BarcodeScannerPlugin instance;

    @Override
    public void load() {
        instance = this;
        Log.e(TAG, "Plugin loaded, instance set");
    }

    @PluginMethod
    public void scan(PluginCall call) {
        Log.e(TAG, "scan() single-shot called from JavaScript");
        Intent intent = new Intent(getContext(), BarcodeScannerActivity.class);
        intent.putExtra("continuous", false);
        startActivityForResult(call, intent, "handleScanResult");
    }

    @PluginMethod
    public void startContinuousScan(PluginCall call) {
        Log.e(TAG, "startContinuousScan() called from JavaScript");
        Intent intent = new Intent(getContext(), BarcodeScannerActivity.class);
        intent.putExtra("continuous", true);
        getActivity().startActivity(intent);
    }

    @PluginMethod
    public void stopContinuousScan(PluginCall call) {
        Log.e(TAG, "stopContinuousScan() called from JavaScript");
        Intent intent = new Intent("com.aso.app.STOP_SCAN");
        getContext().sendBroadcast(intent);
        call.resolve();
    }

    public static void onBarcodeScanned(String code) {
        Log.e(TAG, "onBarcodeScanned: " + code);
        if (instance != null) {
            JSObject data = new JSObject();
            data.put("barcode", code);
            data.put("success", true);
            instance.notifyListeners("scan", data);
        }
    }

    public static void onScanStopped() {
        Log.e(TAG, "onScanStopped");
        if (instance != null) {
            JSObject data = new JSObject();
            data.put("stopped", true);
            instance.notifyListeners("stopped", data);
        }
    }

    @ActivityCallback
    private void handleScanResult(PluginCall call, ActivityResult result) {
        if (result.getResultCode() == Activity.RESULT_OK && result.getData() != null) {
            String barcode = result.getData().getStringExtra("barcode");
            Log.e(TAG, "Barcode scanned (single): " + barcode);
            JSObject response = new JSObject();
            response.put("success", true);
            response.put("barcode", barcode);
            call.resolve(response);
        } else {
            call.reject("تم إلغاء المسح");
        }
    }
}
