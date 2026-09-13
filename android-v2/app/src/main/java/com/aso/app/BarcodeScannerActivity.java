package com.aso.app;

import android.Manifest;
import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.hardware.Camera;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.view.Gravity;
import android.view.SurfaceHolder;
import android.view.SurfaceView;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.google.mlkit.vision.barcode.BarcodeScanner;
import com.google.mlkit.vision.barcode.BarcodeScanning;
import com.google.mlkit.vision.barcode.common.Barcode;
import com.google.mlkit.vision.common.InputImage;

public class BarcodeScannerActivity extends Activity implements SurfaceHolder.Callback {

    private static final String TAG = "BarcodeScanner";
    private static final int PERMISSION_REQUEST = 100;
    private static final long AUTO_FINISH_DELAY_MS = 5000;
    private static final long CONTINUOUS_COOLDOWN_MS = 800;

    private Camera camera;
    private SurfaceView surfaceView;
    private BarcodeScanner mlScanner;
    private volatile boolean processing = false;
    private boolean continuousMode = false;

    private boolean found = false;
    private String lastResult = null;
    private final Handler autoFinishHandler = new Handler(Looper.getMainLooper());

    private TextView resultOverlay;
    private TextView statusText;
    private long scanCooldownUntil = 0;
    private int scanCount = 0;

    private BroadcastReceiver stopReceiver;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        continuousMode = getIntent().getBooleanExtra("continuous", false);
        Log.e(TAG, "onCreate continuousMode=" + continuousMode);

        requestWindowFeature(Window.FEATURE_NO_TITLE);
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN,
                WindowManager.LayoutParams.FLAG_FULLSCREEN);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(0xFF000000);

        surfaceView = new SurfaceView(this);
        root.addView(surfaceView, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT));

        statusText = new TextView(this);
        statusText.setText("جاري البحث عن باركود...");
        statusText.setTextColor(0xFFFFFFFF);
        statusText.setTextSize(18);
        statusText.setGravity(Gravity.CENTER);
        FrameLayout.LayoutParams textParams = new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.WRAP_CONTENT);
        textParams.gravity = Gravity.BOTTOM;
        textParams.bottomMargin = 100;
        root.addView(statusText, textParams);

        View scanLine = new View(this);
        scanLine.setBackgroundColor(0xFF2A8B8B);
        FrameLayout.LayoutParams lineParams = new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT, 4);
        lineParams.gravity = Gravity.CENTER;
        scanLine.setLayoutParams(lineParams);
        root.addView(scanLine);

        resultOverlay = new TextView(this);
        resultOverlay.setTextColor(0xFFFFFFFF);
        resultOverlay.setTextSize(22);
        resultOverlay.setTypeface(null, Typeface.BOLD);
        resultOverlay.setGravity(Gravity.CENTER);
        resultOverlay.setPadding(32, 24, 32, 24);
        GradientDrawable overlayBg = new GradientDrawable();
        overlayBg.setColor(0xCC000000);
        overlayBg.setCornerRadius(20f);
        resultOverlay.setBackground(overlayBg);
        resultOverlay.setVisibility(View.GONE);
        FrameLayout.LayoutParams overlayParams = new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.WRAP_CONTENT,
                FrameLayout.LayoutParams.WRAP_CONTENT);
        overlayParams.gravity = Gravity.CENTER_HORIZONTAL | Gravity.TOP;
        overlayParams.topMargin = 80;
        root.addView(resultOverlay, overlayParams);

        setContentView(root);

        try {
            mlScanner = BarcodeScanning.getClient();
            Log.e(TAG, "ML Kit scanner initialized OK");
        } catch (Exception e) {
            Log.e(TAG, "ML Kit init FAILED: " + e.getMessage(), e);
            Toast.makeText(this, "فشل تهيئة ماسح الباركود: " + e.getMessage(), Toast.LENGTH_LONG).show();
            setResult(RESULT_CANCELED);
            finish();
            return;
        }

        surfaceView.getHolder().addCallback(this);

        if (checkSelfPermission(Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.CAMERA}, PERMISSION_REQUEST);
        }

        if (continuousMode) {
            stopReceiver = new BroadcastReceiver() {
                @Override
                public void onReceive(Context context, Intent intent) {
                    Log.e(TAG, "Stop broadcast received");
                    finish();
                }
            };
            registerReceiver(stopReceiver, new IntentFilter("com.aso.app.STOP_SCAN"), Context.RECEIVER_NOT_EXPORTED);
        }
    }

    @Override
    public void onBackPressed() {
        Log.e(TAG, "onBackPressed continuousMode=" + continuousMode);
        if (continuousMode) {
            BarcodeScannerPlugin.onScanStopped();
        }
        finish();
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        if (requestCode == PERMISSION_REQUEST && grantResults.length > 0
                && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
        } else {
            Toast.makeText(this, "يجب منح إذن الكاميرا", Toast.LENGTH_LONG).show();
            setResult(RESULT_CANCELED);
            finish();
        }
    }

    @Override
    public void surfaceCreated(SurfaceHolder holder) {
        Log.e(TAG, "surfaceCreated");
        if (checkSelfPermission(Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            openCamera();
        }
    }

    @Override
    public void surfaceChanged(SurfaceHolder holder, int format, int width, int height) {}

    @Override
    public void surfaceDestroyed(SurfaceHolder holder) {
        releaseCamera();
    }

    private void openCamera() {
        Log.e(TAG, "openCamera() continuousMode=" + continuousMode);
        try {
            camera = Camera.open(Camera.CameraInfo.CAMERA_FACING_BACK);
            camera.setPreviewDisplay(surfaceView.getHolder());
            camera.setDisplayOrientation(90);

            Camera.Parameters params = camera.getParameters();
            params.setFocusMode(Camera.Parameters.FOCUS_MODE_CONTINUOUS_PICTURE);
            camera.setParameters(params);

            camera.setPreviewCallback((data, cam) -> {
                if (processing) return;
                if (System.currentTimeMillis() < scanCooldownUntil) return;

                if (!continuousMode && found) return;

                processing = true;

                try {
                    Camera.Size size = cam.getParameters().getPreviewSize();

                    InputImage image = InputImage.fromByteArray(
                            data,
                            size.width,
                            size.height,
                            90,
                            InputImage.IMAGE_FORMAT_NV21
                    );

                    mlScanner.process(image)
                            .addOnSuccessListener(barcodes -> {
                                if (!barcodes.isEmpty()) {
                                    String code = null;
                                    for (Barcode b : barcodes) {
                                        code = b.getRawValue();
                                        if (code == null) code = b.getDisplayValue();
                                        if (code != null) break;
                                    }
                                    if (code != null) {
                                        Log.e(TAG, "BARCODE FOUND: " + code + " continuousMode=" + continuousMode);
                                        final String barcodeCode = code;

                                        if (continuousMode) {
                                            scanCooldownUntil = System.currentTimeMillis() + CONTINUOUS_COOLDOWN_MS;
                                            scanCount++;

                                            BarcodeScannerPlugin.onBarcodeScanned(barcodeCode);

                                            runOnUiThread(() -> {
                                                resultOverlay.setText("✅ " + barcodeCode + " (#" + scanCount + ")");
                                                resultOverlay.setVisibility(View.VISIBLE);
                                                autoFinishHandler.removeCallbacksAndMessages(null);
                                                autoFinishHandler.postDelayed(() -> {
                                                    resultOverlay.setVisibility(View.GONE);
                                                }, 2000);
                                                statusText.setText("تم قراءة " + scanCount + " باركود — جاري البحث...");
                                            });

                                            processing = false;
                                        } else {
                                            found = true;
                                            lastResult = code;
                                            scanCooldownUntil = System.currentTimeMillis() + 3000;

                                            runOnUiThread(() -> {
                                                resultOverlay.setText("✅ " + barcodeCode);
                                                resultOverlay.setVisibility(View.VISIBLE);
                                            });

                                            autoFinishHandler.removeCallbacksAndMessages(null);
                                            autoFinishHandler.postDelayed(() -> {
                                                if (lastResult != null) {
                                                    Intent resultIntent = new Intent();
                                                    resultIntent.putExtra("barcode", lastResult);
                                                    setResult(RESULT_OK, resultIntent);
                                                    finish();
                                                }
                                            }, AUTO_FINISH_DELAY_MS);

                                            autoFinishHandler.postDelayed(() -> {
                                                found = false;
                                            }, 3000);
                                        }
                                    } else {
                                        processing = false;
                                    }
                                } else {
                                    processing = false;
                                }
                            })
                            .addOnFailureListener(e -> {
                                Log.e(TAG, "ML Kit fail: " + e.getClass().getSimpleName() + ": " + e.getMessage());
                                processing = false;
                            });

                    return;
                } catch (Exception e) {
                    Log.e(TAG, "Frame error: " + e.getMessage(), e);
                }
                processing = false;
            });

            camera.startPreview();
            Log.e(TAG, "Preview started OK");
        } catch (Exception e) {
            Log.e(TAG, "Camera open FAILED: " + e.getMessage(), e);
            Toast.makeText(this, "فشل فتح الكاميرا: " + e.getMessage(), Toast.LENGTH_LONG).show();
            setResult(RESULT_CANCELED);
            finish();
        }
    }

    private void releaseCamera() {
        if (camera != null) {
            try {
                camera.setPreviewCallback(null);
                camera.stopPreview();
                camera.release();
            } catch (Exception e) {}
            camera = null;
        }
    }

    @Override
    protected void onDestroy() {
        Log.e(TAG, "onDestroy continuousMode=" + continuousMode);
        autoFinishHandler.removeCallbacksAndMessages(null);
        releaseCamera();
        if (mlScanner != null) {
            try { mlScanner.close(); } catch (Exception e) {}
            mlScanner = null;
        }
        if (stopReceiver != null) {
            try { unregisterReceiver(stopReceiver); } catch (Exception e) {}
            stopReceiver = null;
        }
        super.onDestroy();
    }
}
