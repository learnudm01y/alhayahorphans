@echo off
REM Docker Setup Script for Laravel Project (Windows)
echo ======================================
echo   Laravel Docker Setup Script
echo ======================================
echo.

REM Stop existing containers
echo [1/12] Stopping existing containers...
docker-compose down

REM Build containers
echo [2/12] Building Docker containers...
docker-compose build --no-cache

REM Start containers
echo [3/12] Starting Docker containers...
docker-compose up -d

REM Wait for MySQL to be ready
echo [4/12] Waiting for MySQL to be ready...
timeout /t 20 /nobreak >nul

REM Check if .env file exists
if not exist .env (
    echo [5/12] Creating .env file from .env.example...
    docker-compose exec -T app cp .env.example .env
) else (
    echo [5/12] .env file already exists, skipping...
)

REM Install Composer dependencies
echo [6/12] Installing Composer dependencies...
docker-compose exec -T app composer install --optimize-autoloader --no-interaction

REM Generate application key
echo [7/12] Generating application key...
docker-compose exec -T app php artisan key:generate --force

REM Run migrations
echo [8/12] Running database migrations...
docker-compose exec -T app php artisan migrate --force

REM Clear and cache config
echo [9/12] Clearing cache...
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan view:clear

echo [10/12] Caching configuration...
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

REM Create storage link
echo [11/12] Creating storage link...
docker-compose exec -T app php artisan storage:link

REM Set permissions
echo [12/12] Setting permissions...
docker-compose exec -T app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker-compose exec -T app chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo.
echo ======================================
echo Setup completed successfully!
echo ======================================
echo.
echo Access points:
echo    - Application: http://localhost:8000
echo    - phpMyAdmin:  http://localhost:8080
echo.
echo Database credentials:
echo    - Host: localhost
echo    - Port: 3306
echo    - Database: laravel
echo    - Username: laravel
echo    - Password: secret
echo    - Root Password: root
echo.
echo Useful commands:
echo    - View logs: docker-compose logs -f
echo    - Stop containers: docker-compose down
echo    - Restart: docker-compose restart
echo    - Execute artisan: docker-compose exec app php artisan [command]
echo.
pause
