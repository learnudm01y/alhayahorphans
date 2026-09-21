# 📹 Quick Reference - Camera Memory Management

## 🚀 JavaScript API

```javascript
// قبل فتح الكاميرا (MANDATORY!)
const ready = window.CameraBridge.prepareForRecording();
if (!ready) {
    alert('الذاكرة غير كافية');
    return;
}

// فتح الكاميرا بأمان
openCamera();

// مراقبة أثناء التصوير (كل 5 ثوانٍ)
setInterval(() => {
    const mem = JSON.parse(window.CameraBridge.getMemoryInfo());
    if (mem.availableMemory < 100 * 1024 * 1024) {
        stopRecording();
        window.CameraBridge.onLowMemory();
    }
}, 5000);

// بعد الانتهاء (MANDATORY!)
window.CameraBridge.onRecordingFinished();
```

## 📊 Memory Thresholds

| Available Memory | Status | Action |
|------------------|--------|--------|
| >= 150 MB | ✅ Good | Record freely |
| 100-150 MB | ⚠️ Low | Short videos only |
| < 100 MB | ❌ Critical | **STOP immediately** |

## 🔍 Debugging

```bash
# Monitor logs
adb logcat | grep -E "CameraMemoryManager|CameraBridge"

# Test from Chrome DevTools
window.CameraBridge.logMemoryStatus();
```

## ⚠️ CRITICAL Rules

1. **ALWAYS** call `prepareForRecording()` before opening camera
2. **ALWAYS** call `onRecordingFinished()` after recording
3. **NEVER** ignore `onLowMemory()` - stop immediately!
4. **MONITOR** memory every 5 seconds during recording

## 📚 Full Guide

See [CAMERA_OOM_FIX_GUIDE.md](CAMERA_OOM_FIX_GUIDE.md) for complete documentation.
