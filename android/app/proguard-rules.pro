# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# If your project uses WebView with JS, uncomment the following
# and specify the fully qualified class name to the JavaScript interface
# class:
#-keepclassmembers class fqcn.of.javascript.interface.for.webview {
#   public *;
#}

# Uncomment this to preserve the line number information for
# debugging stack traces.
-keepattributes SourceFile,LineNumberTable

# If you keep the line number information, uncomment this to
# hide the original source file name.
#-renamesourcefileattribute SourceFile

# ═══════════════════════════════════════════════════════════════════════════
# ✨ CRITICAL PROGUARD RULES FOR BACKGROUND SERVICES & RECEIVERS
# ═══════════════════════════════════════════════════════════════════════════

# Keep all Capacitor Plugins (JavaScript Interface)
-keep class com.aso.app.** { *; }
-keep class org.alhayah.sponsorships.** { *; }

# Keep all Android Components (Services, Receivers, Application)
-keep class * extends android.app.Service
-keep class * extends android.app.IntentService
-keep class * extends android.content.BroadcastReceiver
-keep class * extends android.app.Application

# Keep all Activities
-keep class * extends androidx.appcompat.app.AppCompatActivity
-keep class * extends com.getcapacitor.BridgeActivity

# Keep Capacitor Plugin methods
-keepclassmembers class * extends com.getcapacitor.Plugin {
    @com.getcapacitor.annotation.CapacitorPlugin *;
    @com.getcapacitor.PluginMethod *;
    public *;
}

# Keep JavaScript Interface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep Database Helpers
-keep class * extends android.database.sqlite.SQLiteOpenHelper {
    *;
}

# Keep WorkManager Workers
-keep class * extends androidx.work.Worker
-keep class * extends androidx.work.ListenableWorker

# Keep AlarmManager Receivers (CRITICAL)
-keep class com.aso.app.UploadAlarmReceiver { *; }
-keep class org.alhayah.sponsorships.DataSyncAlarmReceiver { *; }

# Keep Boot Receivers (CRITICAL)
-keep class com.aso.app.UploadBootReceiver { *; }
-keep class org.alhayah.sponsorships.DataSyncBootReceiver { *; }

# Keep Network Monitors
-keep class com.aso.app.NetworkMonitor { *; }
-keep class org.alhayah.sponsorships.DataSyncNetworkMonitor { *; }

# Keep Foreground Services (CRITICAL)
-keep class com.aso.app.UploadForegroundService { *; }
-keep class org.alhayah.sponsorships.DataSyncForegroundService { *; }

# Keep Application class
-keep class com.aso.app.AutoUploadApplication { *; }

# Keep all public methods in plugins
-keepclassmembers class com.aso.app.* {
    public *;
}

# Keep enums
-keepclassmembers enum * { *; }

# Keep Parcelables
-keep class * implements android.os.Parcelable {
    public static final android.os.Parcelable$Creator *;
}

# Keep Serializable
-keepclassmembers class * implements java.io.Serializable {
    static final long serialVersionUID;
    private static final java.io.ObjectStreamField[] serialPersistentFields;
    private void writeObject(java.io.ObjectOutputStream);
    private void readObject(java.io.ObjectInputStream);
    java.lang.Object writeReplace();
    java.lang.Object readResolve();
}

# Keep native methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep annotations
-keepattributes *Annotation*
-keepattributes Signature
-keepattributes Exceptions

# Capacitor specific rules
-keep class com.getcapacitor.** { *; }
-dontwarn com.getcapacitor.**

# AndroidX rules
-keep class androidx.** { *; }
-dontwarn androidx.**

# Firebase/Google (if used)
-dontwarn com.google.**

# OkHttp (if used)
-dontwarn okhttp3.**
-dontwarn okio.**

# ═══════════════════════════════════════════════════════════════════════════
# END OF CRITICAL RULES
# ═══════════════════════════════════════════════════════════════════════════
