#!/bin/bash

# مسار تثبيت أداة Speedtest CLI من Ookla
# يجب تشغيل هذا الملف بصلاحيات المدير (sudo)

echo "🚀 بدء تثبيت Speedtest CLI من Ookla..."

# التحقق من نظام التشغيل
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    echo "✅ تم اكتشاف نظام Linux"

    # تثبيت على Ubuntu/Debian
    if command -v apt-get &> /dev/null; then
        echo "📦 تثبيت على Ubuntu/Debian..."
        curl -s https://install.speedtest.net/app/cli/install.sh | sudo bash

    # تثبيت على CentOS/RHEL
    elif command -v yum &> /dev/null; then
        echo "📦 تثبيت على CentOS/RHEL..."
        curl -s https://install.speedtest.net/app/cli/install.sh | sudo bash

    # تثبيت على Fedora
    elif command -v dnf &> /dev/null; then
        echo "📦 تثبيت على Fedora..."
        curl -s https://install.speedtest.net/app/cli/install.sh | sudo bash

    else
        echo "❌ نظام التشغيل غير مدعوم أو غير معروف"
        exit 1
    fi

elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo "✅ تم اكتشاف نظام macOS"

    # تحقق من وجود Homebrew
    if command -v brew &> /dev/null; then
        echo "📦 تثبيت عبر Homebrew..."
        brew tap teamookla/speedtest
        brew install speedtest --force
    else
        echo "❌ Homebrew غير مثبت. يرجى تثبيته أولاً من https://brew.sh/"
        exit 1
    fi

elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "win32" ]]; then
    echo "✅ تم اكتشاف نظام Windows"
    echo "📥 يرجى تحميل الملف التنفيذي من: https://www.speedtest.net/apps/cli"
    echo "📋 أو استخدام Chocolatey: choco install speedtest"

else
    echo "❌ نظام التشغيل غير مدعوم"
    exit 1
fi

# التحقق من التثبيت
echo "🔍 التحقق من التثبيت..."
if command -v speedtest &> /dev/null; then
    echo "✅ تم تثبيت Speedtest CLI بنجاح!"
    echo "📊 الإصدار المثبت:"
    speedtest --version

    echo ""
    echo "🎯 اختبار سريع:"
    speedtest --version --format=json

    echo ""
    echo "✨ يمكنك الآن استخدام أداة قياس السرعة في مشروع Laravel"
    echo "🌐 تأكد من أن الخادم متصل بالإنترنت لإجراء الاختبارات"

else
    echo "❌ فشل في تثبيت Speedtest CLI"
    echo "💡 يرجى تثبيته يدوياً من: https://www.speedtest.net/apps/cli"
    exit 1
fi

echo ""
echo "🔧 للاستخدام في Laravel:"
echo "1. تأكد من أن مسار /api/speedtest يعمل بشكل صحيح"
echo "2. تحقق من أن Apache/Nginx لديه الصلاحيات لتشغيل الأوامر"
echo "3. قم بتشغيل: php artisan route:list للتأكد من وجود المسارات"
echo ""
echo "✅ التثبيت مكتمل!"
