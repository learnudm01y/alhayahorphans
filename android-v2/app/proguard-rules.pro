# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# ═══════════════════════════════════════════════════════════════════
# 🛡️ WebView Protection - Prevent Chromium crashes
# ═══════════════════════════════════════════════════════════════════

# Keep all WebView classes
-keep class android.webkit.** { *; }
-keepclassmembers class android.webkit.** { *; }

# Keep WebView interfaces for JavaScript
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep WebView resources
-keepclasseswithmembers class * {
    public <init>(android.content.Context, android.util.AttributeSet);
}

# Keep WebView WebChromeClient and WebViewClient
-keep class * extends android.webkit.WebViewClient { *; }
-keep class * extends android.webkit.WebChromeClient { *; }

# Prevent obfuscation of WebView callbacks
-keepclassmembers class * extends android.webkit.WebViewClient {
    public void *(android.webkit.WebView, java.lang.String);
    public void *(android.webkit.WebView, java.lang.String, android.graphics.Bitmap);
    public boolean *(android.webkit.WebView, java.lang.String);
}

# ═══════════════════════════════════════════════════════════════════
# Capacitor Framework
# ═══════════════════════════════════════════════════════════════════
-keep class com.getcapacitor.** { *; }
-keepclassmembers class com.getcapacitor.** { *; }

# ═══════════════════════════════════════════════════════════════════
# Application Classes
# ═══════════════════════════════════════════════════════════════════
-keep class com.aso.app.** { *; }
-keepclassmembers class com.aso.app.** { *; }

# If your project uses WebView with JS, uncomment the following
# and specify the fully qualified class name to the JavaScript interface
# class:
#-keepclassmembers class fqcn.of.javascript.interface.for.webview {
#   public *;
#}

# Uncomment this to preserve the line number information for
# debugging stack traces.
-keepattributes SourceFile,LineNumberTable
-keepattributes *Annotation*
-keepattributes Signature
-keepattributes Exceptions

# If you keep the line number information, uncomment this to
# hide the original source file name.
#-renamesourcefileattribute SourceFile
