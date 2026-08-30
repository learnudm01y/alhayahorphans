package com.aso.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.provider.Settings;
import android.telephony.PhoneStateListener;
import android.telephony.TelephonyManager;
import android.util.Log;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.View;
import android.view.WindowManager;

import org.json.JSONObject;

import java.util.concurrent.Executors;

public class CallerInfoService extends Service {

    private static final String TAG = "CallerInfoService";
    private static final String CHANNEL_ID = "caller_info_channel";
    private static final int NOTIFICATION_ID = 7777;

    private WindowManager windowManager;
    private View overlayView;
    private Handler mainHandler;
    private TelephonyManager telephonyManager;
    private PhoneStateListener phoneStateListener;
    private String lastIncomingNumber = "";
    private boolean isListening = false;

    @Override
    public void onCreate() {
        super.onCreate();
        windowManager = (WindowManager) getSystemService(WINDOW_SERVICE);
        mainHandler = new Handler(Looper.getMainLooper());
        telephonyManager = (TelephonyManager) getSystemService(TELEPHONY_SERVICE);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && "SHOW_CALLER_INFO".equals(intent.getAction())) {
            String phoneNumber = intent.getStringExtra("phoneNumber");
            if (phoneNumber != null && !phoneNumber.isEmpty()) {
                Log.e(TAG, "Direct search: " + phoneNumber);
                searchAndShow(phoneNumber);
                return START_STICKY;
            }
        }

        startForegroundNotification();
        startPhoneStateListener();

        Log.e(TAG, "CallerInfoService started as foreground — listening for calls");
        return START_STICKY;
    }

    private void startForegroundNotification() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "مراقبة المكالمات",
                    NotificationManager.IMPORTANCE_LOW);
            channel.setDescription("مراقبة المكالمات الواردة لعرض معلومات المتصل");
            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            nm.createNotificationChannel(channel);
        }

        Notification notification = new Notification.Builder(this, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_menu_call)
                .setContentTitle("مراقبة المكالمات")
                .setContentText("جاري مراقبة المكالمات الواردة...")
                .setOngoing(true)
                .build();

        startForeground(NOTIFICATION_ID, notification);
    }

    @SuppressWarnings("deprecation")
    private void startPhoneStateListener() {
        if (isListening) return;

        phoneStateListener = new PhoneStateListener() {
            @Override
            public void onCallStateChanged(int state, String phoneNumber) {
                super.onCallStateChanged(state, phoneNumber);

                Log.e(TAG, "Phone state: " + state + " number: " + phoneNumber);

                if (state == TelephonyManager.CALL_STATE_RINGING) {
                    if (phoneNumber != null && !phoneNumber.isEmpty()) {
                        lastIncomingNumber = phoneNumber;
                        Log.e(TAG, "INCOMING CALL DETECTED: " + phoneNumber);
                        searchAndShow(phoneNumber);
                    } else {
                        Log.e(TAG, "RINGING but no number — will check call log");
                        mainHandler.postDelayed(() -> fetchLastIncomingFromCallLog(), 1500);
                    }
                }
            }
        };

        try {
            telephonyManager.listen(phoneStateListener, PhoneStateListener.LISTEN_CALL_STATE);
            isListening = true;
            Log.e(TAG, "PhoneStateListener registered successfully");
        } catch (SecurityException e) {
            Log.e(TAG, "SecurityException — READ_PHONE_STATE not granted", e);
        } catch (Exception e) {
            Log.e(TAG, "Failed to register PhoneStateListener", e);
        }
    }

    private void fetchLastIncomingFromCallLog() {
        Executors.newSingleThreadExecutor().execute(() -> {
            try {
                android.database.Cursor cursor = getContentResolver().query(
                        android.provider.CallLog.Calls.CONTENT_URI,
                        new String[]{android.provider.CallLog.Calls.NUMBER},
                        android.provider.CallLog.Calls.TYPE + " = ?",
                        new String[]{String.valueOf(android.provider.CallLog.Calls.INCOMING_TYPE)},
                        android.provider.CallLog.Calls.DATE + " DESC LIMIT 1");

                if (cursor != null && cursor.moveToFirst()) {
                    String number = cursor.getString(0);
                    cursor.close();
                    if (number != null && !number.isEmpty()) {
                        Log.e(TAG, "Last incoming from call log: " + number);
                        mainHandler.post(() -> searchAndShow(number));
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to read call log", e);
            }
        });
    }

    private void searchAndShow(String phoneNumber) {
        Executors.newSingleThreadExecutor().execute(() -> {
            try {
                RelatedDataDatabaseHelper db = RelatedDataDatabaseHelper.getInstance(this);
                JSONObject callerData = db.getDataByPhoneNumber(phoneNumber);

                String name = "غير معروف";
                String fileId = "";
                String address = "";
                String phone = phoneNumber;
                String individuals = "";
                String sectionId = "";

                if (callerData != null) {
                    String firstName = callerData.optString("data_first_name", "");
                    String fatherName = callerData.optString("data_father_name", "");
                    String grandName = callerData.optString("data_grand_father_name", "");
                    String familyName = callerData.optString("data_family_name", "");
                    name = (firstName + " " + fatherName + " " + grandName + " " + familyName).trim();
                    if (name.isEmpty()) name = "غير معروف";

                    fileId = callerData.optString("file_id_number", "");
                    address = callerData.optString("data_current_address", "");
                    phone = callerData.optString("data_phone_number", phoneNumber);
                    individuals = callerData.optString("data_number_of_individuals", "");
                    sectionId = callerData.optString("data_section_id", "");
                }

                String finalName = name;
                String finalFileId = fileId;
                String finalAddress = address;
                String finalPhone = phone;
                String finalIndividuals = individuals;
                String finalSectionId = sectionId;

                mainHandler.post(() -> showOverlay(finalName, finalFileId, finalAddress, finalPhone, finalIndividuals, finalSectionId));

            } catch (Exception e) {
                Log.e(TAG, "Search error", e);
                mainHandler.post(() -> showOverlay("خطأ في البحث", "", "", phoneNumber, "", ""));
            }
        });
    }

    private void showOverlay(String name, String fileId, String address, String phone, String individuals, String sectionId) {
        removeOverlay();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this)) {
            Log.e(TAG, "No overlay permission — using notification");
            showNotification(name, fileId, phone);
            return;
        }

        try {
            overlayView = LayoutInflater.from(this).inflate(R.layout.caller_info_overlay, null);

            overlayView.findViewById(R.id.tvCallerName).setEnabled(false);
            android.widget.TextView tvName = overlayView.findViewById(R.id.tvCallerName);
            android.widget.TextView tvFileId = overlayView.findViewById(R.id.tvCallerFileId);
            android.widget.TextView tvPhone = overlayView.findViewById(R.id.tvCallerPhone);
            android.widget.TextView tvAddress = overlayView.findViewById(R.id.tvCallerAddress);
            android.widget.TextView tvIndividuals = overlayView.findViewById(R.id.tvCallerIndividuals);
            android.widget.TextView tvSectionId = overlayView.findViewById(R.id.tvCallerSectionId);

            tvName.setText(name);
            tvFileId.setText(fileId.isEmpty() ? "—" : "رقم الملف: " + fileId);
            tvPhone.setText("الهاتف: " + phone);
            tvAddress.setText(address.isEmpty() ? "" : "العنوان: " + address);
            tvIndividuals.setText(individuals.isEmpty() ? "" : "عدد الأفراد: " + individuals);
            tvSectionId.setText(sectionId.isEmpty() ? "" : "الشعبة: " + sectionId);

            if (name.equals("غير معروف") || name.startsWith("خطأ")) {
                tvName.setTextColor(0xFFFF0000);
            }

            overlayView.findViewById(R.id.btnDismissCaller).setOnClickListener(v -> removeOverlay());

            WindowManager.LayoutParams params = new WindowManager.LayoutParams(
                    WindowManager.LayoutParams.MATCH_PARENT,
                    WindowManager.LayoutParams.WRAP_CONTENT,
                    WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
                    WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE | WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED,
                    PixelFormat.TRANSLUCENT);
            params.gravity = Gravity.TOP | Gravity.CENTER_HORIZONTAL;
            params.y = 50;

            windowManager.addView(overlayView, params);
            Log.e(TAG, "Overlay shown: " + name);

        } catch (Exception e) {
            Log.e(TAG, "Overlay error", e);
            showNotification(name, fileId, phone);
        }
    }

    private void showNotification(String name, String fileId, String phone) {
        try {
            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            String channelId = "caller_info";

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                NotificationChannel channel = new NotificationChannel(channelId, "معلومات المتصل", NotificationManager.IMPORTANCE_HIGH);
                channel.setDescription("عرض معلومات المتصل عند المكالمة الواردة");
                nm.createNotificationChannel(channel);
            }

            String text = "رقم الملف: " + (fileId.isEmpty() ? "—" : fileId) + "\nالهاتف: " + phone;

            Notification notification = new Notification.Builder(this, channelId)
                    .setSmallIcon(android.R.drawable.ic_menu_call)
                    .setContentTitle(name)
                    .setContentText(text)
                    .setStyle(new Notification.BigTextStyle().bigText(text))
                    .setAutoCancel(true)
                    .setPriority(Notification.PRIORITY_HIGH)
                    .build();

            nm.notify(9999, notification);
            Log.e(TAG, "Notification shown: " + name);
        } catch (Exception e) {
            Log.e(TAG, "Notification error", e);
        }
    }

    private void removeOverlay() {
        if (overlayView != null) {
            try {
                windowManager.removeView(overlayView);
            } catch (Exception e) {}
            overlayView = null;
        }
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        if (phoneStateListener != null && isListening) {
            telephonyManager.listen(phoneStateListener, PhoneStateListener.LISTEN_NONE);
            isListening = false;
        }
        removeOverlay();
        super.onDestroy();
    }
}
