# 🚀 دليل تثبيت Speedtest CLI للإنتاج

## 📋 التثبيت على أنظمة التشغيل المختلفة

### 🖥️ Windows (للتطوير والخادم)
```powershell
# الطريقة الأولى: باستخدام Chocolatey (الأسهل)
choco install speedtest

# الطريقة الثانية: تحميل مباشر
# 1. انتقل إلى: https://www.speedtest.net/apps/cli
# 2. حمل ملف speedtest.exe
# 3. ضعه في مجلد C:\Windows\System32 أو أي مجلد في PATH
```

### 🐧 Linux Ubuntu/Debian (للخادم)
```bash
# تثبيت مباشر من Ookla
curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | sudo bash
sudo apt-get install speedtest

# أو استخدام السكريبت المخصص
wget -qO- https://install.speedtest.net/app/cli/install.sh | sudo bash
```

### 🔴 CentOS/RHEL/Fedora
```bash
# للأنظمة القائمة على RPM
curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.rpm.sh | sudo bash
sudo yum install speedtest

# أو
sudo dnf install speedtest
```

### 🍎 macOS
```bash
# باستخدام Homebrew
brew tap teamookla/speedtest
brew install speedtest --force
```

## 🔧 التحقق من التثبيت
```bash
# فحص الإصدار
speedtest --version

# اختبار سريع
speedtest --format=json
```

## ⚙️ إعداد PHP و Laravel

### متطلبات PHP
```php
// تأكد من أن PHP يمكنه تشغيل الأوامر الخارجية
exec_enabled = On
shell_exec_enabled = On
system_enabled = On

// أو في Laravel
// تأكد أن Symfony Process متوفر
composer require symfony/process
```

## 🐳 Docker Setup (للخوادم السحابية)
```dockerfile
FROM php:8.2-fpm

# تثبيت Speedtest CLI
RUN curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | bash && \
    apt-get update && \
    apt-get install -y speedtest && \
    speedtest --version
```

## 🌐 إعداد الخادم

### Apache
```apache
# تأكد أن PHP يمكنه تشغيل الأوامر
# في .htaccess أو httpd.conf
php_admin_value[disable_functions] = ""
```

### Nginx + PHP-FPM
```nginx
# في ملف إعداد PHP-FPM
; إزالة القيود على الأوامر
disable_functions = 
```

## 🔒 الأمان والصلاحيات
```bash
# إعطاء صلاحيات للمستخدم
sudo usermod -a -G speedtest www-data

# أو إنشاء wrapper script
sudo cat > /usr/local/bin/speedtest-wrapper << 'EOF'
#!/bin/bash
/usr/bin/speedtest "$@"
EOF

sudo chmod +x /usr/local/bin/speedtest-wrapper
```

## 📊 اختبار التكامل مع Laravel
```bash
# اختبار من سطر الأوامر
php artisan tinker
>>> \Symfony\Component\Process\Process::fromShellCommandline('speedtest --version')->run();
```
