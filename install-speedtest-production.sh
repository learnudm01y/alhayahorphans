#!/bin/bash

# ===========================================
# 🚀 Speedtest CLI Production Installer
# ===========================================
# نسخة محسنة للإنتاج مع دعم أنظمة متعددة
# تاريخ الإنشاء: 2025-07-17

set -e  # إيقاف التنفيذ عند أول خطأ

# الألوان للعرض
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# دالة للطباعة الملونة
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# التحقق من صلاحيات المدير
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "يجب تشغيل هذا السكريبت بصلاحيات المدير (sudo)"
        exit 1
    fi
}

# اكتشاف نظام التشغيل
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &> /dev/null; then
            OS="ubuntu"
            print_status "تم اكتشاف نظام Ubuntu/Debian"
        elif command -v yum &> /dev/null; then
            OS="centos"
            print_status "تم اكتشاف نظام CentOS/RHEL"
        elif command -v dnf &> /dev/null; then
            OS="fedora"
            print_status "تم اكتشاف نظام Fedora"
        else
            print_error "نظام Linux غير مدعوم"
            exit 1
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
        print_status "تم اكتشاف نظام macOS"
    else
        print_error "نظام التشغيل غير مدعوم"
        exit 1
    fi
}

# تثبيت التبعيات
install_dependencies() {
    print_status "تثبيت التبعيات المطلوبة..."

    case $OS in
        ubuntu)
            apt-get update
            apt-get install -y curl wget gnupg2 software-properties-common
            ;;
        centos)
            yum install -y curl wget gnupg2
            ;;
        fedora)
            dnf install -y curl wget gnupg2
            ;;
        macos)
            if ! command -v brew &> /dev/null; then
                print_error "Homebrew غير مثبت. يرجى تثبيته أولاً"
                exit 1
            fi
            ;;
    esac
}

# تثبيت Speedtest CLI
install_speedtest() {
    print_status "تثبيت Speedtest CLI..."

    case $OS in
        ubuntu)
            # طريقة Ookla الرسمية
            curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | bash
            apt-get update
            apt-get install -y speedtest
            ;;
        centos)
            curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.rpm.sh | bash
            yum install -y speedtest
            ;;
        fedora)
            curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.rpm.sh | bash
            dnf install -y speedtest
            ;;
        macos)
            brew tap teamookla/speedtest
            brew install speedtest --force
            ;;
    esac
}

# إعداد الصلاحيات
setup_permissions() {
    print_status "إعداد الصلاحيات..."

    # إنشاء مجموعة speedtest
    if ! getent group speedtest > /dev/null 2>&1; then
        groupadd speedtest
    fi

    # إضافة مستخدم الويب إلى المجموعة
    if command -v apache2 &> /dev/null; then
        usermod -a -G speedtest www-data
    elif command -v nginx &> /dev/null; then
        usermod -a -G speedtest www-data
    fi

    # تحديد موقع Speedtest
    SPEEDTEST_PATH=$(which speedtest)
    if [[ -n "$SPEEDTEST_PATH" ]]; then
        chown root:speedtest "$SPEEDTEST_PATH"
        chmod 750 "$SPEEDTEST_PATH"
    fi
}

# إنشاء wrapper script آمن
create_wrapper() {
    print_status "إنشاء wrapper script آمن..."

    cat > /usr/local/bin/speedtest-safe << 'EOF'
#!/bin/bash
# Speedtest CLI Wrapper للأمان
# يحد من الخيارات المسموحة

allowed_args="--version --help --format=json --accept-license --accept-gdpr"

# التحقق من المعاملات
for arg in "$@"; do
    if [[ ! "$allowed_args" =~ $arg ]] && [[ ! "$arg" =~ ^--server-id=[0-9]+$ ]]; then
        echo "Error: Argument '$arg' not allowed"
        exit 1
    fi
done

# تنفيذ الأمر
exec /usr/bin/speedtest "$@"
EOF

    chmod +x /usr/local/bin/speedtest-safe
    chown root:speedtest /usr/local/bin/speedtest-safe
}

# اختبار التثبيت
test_installation() {
    print_status "اختبار التثبيت..."

    # اختبار الإصدار
    if speedtest --version &> /dev/null; then
        print_success "✓ تم اكتشاف Speedtest CLI بنجاح"
        VERSION=$(speedtest --version)
        print_status "الإصدار: $VERSION"
    else
        print_error "✗ فشل في اكتشاف Speedtest CLI"
        exit 1
    fi

    # اختبار JSON format
    print_status "اختبار تنسيق JSON..."
    if timeout 30 speedtest --format=json --accept-license --accept-gdpr &> /dev/null; then
        print_success "✓ تنسيق JSON يعمل بشكل صحيح"
    else
        print_warning "⚠ قد تحتاج لقبول الترخيص يدوياً"
    fi
}

# إنشاء ملف إعداد Laravel
create_laravel_config() {
    print_status "إنشاء ملف إعداد Laravel..."

    cat > /tmp/speedtest-config.php << 'EOF'
<?php
// إعدادات Speedtest CLI للإنتاج
return [
    'speedtest' => [
        'enabled' => true,
        'binary_path' => '/usr/local/bin/speedtest-safe',
        'timeout' => 120,
        'max_attempts' => 3,
        'default_args' => [
            '--format=json',
            '--accept-license',
            '--accept-gdpr'
        ],
        'fallback_enabled' => true,
        'cache_results' => true,
        'cache_duration' => 300, // 5 minutes
    ]
];
EOF

    print_status "ملف الإعداد محفوظ في: /tmp/speedtest-config.php"
    print_status "يمكنك نسخه إلى: config/speedtest.php"
}

# الدالة الرئيسية
main() {
    echo "=================================================="
    echo "🚀 Speedtest CLI Production Installer"
    echo "=================================================="
    echo ""

    check_root
    detect_os
    install_dependencies
    install_speedtest
    setup_permissions
    create_wrapper
    test_installation
    create_laravel_config

    echo ""
    echo "=================================================="
    print_success "✅ تم التثبيت بنجاح!"
    echo "=================================================="
    echo ""
    print_status "الخطوات التالية:"
    echo "1. انسخ ملف الإعداد إلى مشروع Laravel"
    echo "2. أعد تشغيل خادم الويب"
    echo "3. اختبر API: /api/speedtest"
    echo ""
    print_status "للاختبار اليدوي:"
    echo "speedtest --version"
    echo "speedtest --format=json --accept-license --accept-gdpr"
    echo ""
    print_warning "تأكد من إعدادات الأمان والجدار الناري"
}

# تشغيل الدالة الرئيسية
main "$@"
