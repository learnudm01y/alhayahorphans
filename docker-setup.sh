#!/bin/bash

# Docker Setup Script for Laravel Project
echo "======================================"
echo "  Laravel Docker Setup Script"
echo "======================================"
echo ""

# Stop existing containers
echo "🛑 Stopping existing containers..."
docker-compose down

# Remove old volumes (optional - comment out if you want to keep data)
# echo "🗑️  Removing old volumes..."
# docker volume rm aso-copy_dbdata 2>/dev/null || true

# Build containers
echo "🔨 Building Docker containers..."
docker-compose build --no-cache

# Start containers
echo "🚀 Starting Docker containers..."
docker-compose up -d

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 15

# Check if .env file exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    docker-compose exec app cp .env.example .env
fi

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
docker-compose exec app composer install --optimize-autoloader

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Run migrations
echo "🗄️  Running database migrations..."
docker-compose exec app php artisan migrate --force

# Seed database (optional)
# echo "🌱 Seeding database..."
# docker-compose exec app php artisan db:seed

# Clear and cache config
echo "🧹 Clearing and caching configuration..."
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Create storage link
echo "🔗 Creating storage link..."
docker-compose exec app php artisan storage:link

# Set permissions
echo "🔐 Setting permissions..."
docker-compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo ""
echo "======================================"
echo "✅ Setup completed successfully!"
echo "======================================"
echo ""
echo "📌 Access points:"
echo "   - Application: http://localhost:8000"
echo "   - phpMyAdmin:  http://localhost:8080"
echo ""
echo "📌 Database credentials:"
echo "   - Host: db (or localhost from host machine)"
echo "   - Port: 3306"
echo "   - Database: laravel"
echo "   - Username: laravel"
echo "   - Password: secret"
echo "   - Root Password: root"
echo ""
echo "📌 Useful commands:"
echo "   - View logs: docker-compose logs -f"
echo "   - Stop containers: docker-compose down"
echo "   - Restart: docker-compose restart"
echo "   - Execute artisan: docker-compose exec app php artisan [command]"
echo ""
