package com.aso.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import android.telephony.TelephonyManager;
import android.util.Log;

public class IncomingCallReceiver extends BroadcastReceiver {

    private static final String TAG = "IncomingCallReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null || intent.getAction() == null) return;

        if (intent.getAction().equals(TelephonyManager.ACTION_PHONE_STATE_CHANGED)) {
            Bundle extras = intent.getExtras();
            if (extras == null) return;

            String state = extras.getString(TelephonyManager.EXTRA_STATE);
            String phoneNumber = extras.getString(TelephonyManager.EXTRA_INCOMING_NUMBER);

            if (TelephonyManager.EXTRA_STATE_RINGING.equals(state) && phoneNumber != null && !phoneNumber.isEmpty()) {
                Log.e(TAG, "INCOMING CALL via broadcast: " + phoneNumber);

                Intent serviceIntent = new Intent(context, CallerInfoService.class);
                serviceIntent.putExtra("phoneNumber", phoneNumber);
                serviceIntent.setAction("SHOW_CALLER_INFO");
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(serviceIntent);
                } else {
                    context.startService(serviceIntent);
                }
            }
        }
    }
}
