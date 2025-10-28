FROM php:8.3-fpm

# تثبيت الأدوات والإضافات المطلوبة
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تحديد مجلد العمل
WORKDIR /var/www

# نسخ ملفات composer أولاً للاستفادة من Docker cache
COPY composer.json composer.lock ./

# تثبيت Composer dependencies
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# نسخ باقي ملفات المشروع
COPY . .

# إنشاء ملف .env من .env.example إذا لم يكن موجوداً
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# تشغيل Laravel commands
RUN composer dump-autoload --optimize \
    && php artisan config:clear \
    && php artisan cache:clear \
    && php artisan route:clear \
    && php artisan view:clear

# صلاحيات التخزين وcache
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# تعريف المستخدم
USER www-data

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
