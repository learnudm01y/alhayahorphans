# Docker Laravel Project

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed
- Docker Compose installed

### Setup Instructions

#### For Windows:
```cmd
docker-setup.bat
```

#### For Linux/Mac:
```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

### Manual Setup

1. **Build and start containers:**
```bash
docker-compose up -d --build
```

2. **Install dependencies:**
```bash
docker-compose exec app composer install
```

3. **Setup environment:**
```bash
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate
```

4. **Run migrations:**
```bash
docker-compose exec app php artisan migrate
```

5. **Create storage link:**
```bash
docker-compose exec app php artisan storage:link
```

## 📌 Access Points

- **Application:** http://localhost:8888
- **phpMyAdmin:** http://localhost:9090

## 🔑 Database Credentials

- **Host:** db (from container) or localhost (from host)
- **Port:** 3306
- **Database:** laravel
- **Username:** laravel
- **Password:** secret
- **Root Password:** root

## 🛠️ Useful Commands

### Container Management
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f web
docker-compose logs -f db
```

### Laravel Artisan Commands
```bash
# Run artisan command
docker-compose exec app php artisan [command]

# Examples:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:list
```

### Composer Commands
```bash
# Install packages
docker-compose exec app composer install

# Update packages
docker-compose exec app composer update

# Require new package
docker-compose exec app composer require vendor/package
```

### Database Operations
```bash
# Access MySQL CLI
docker-compose exec db mysql -u laravel -psecret laravel

# Backup database
docker-compose exec db mysqldump -u laravel -psecret laravel > backup.sql

# Restore database
docker-compose exec -T db mysql -u laravel -psecret laravel < backup.sql
```

### Permission Issues
```bash
# Fix storage permissions
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 775 /var/www/storage
```

## 🏗️ Project Structure

```
.
├── Dockerfile              # PHP-FPM container configuration
├── docker-compose.yml      # Docker services orchestration
├── docker-setup.sh         # Linux/Mac setup script
├── docker-setup.bat        # Windows setup script
├── nginx/
│   └── conf.d/
│       └── default.conf    # Nginx configuration
├── mysql/
│   └── my.cnf             # MySQL configuration
└── ... (Laravel files)
```

## 🐛 Troubleshooting

### Port Already in Use
```bash
# Change ports in docker-compose.yml
# For app: change "8000:80" to "8001:80"
# For db: change "3306:3306" to "3307:3306"
```

### Permission Denied
```bash
# Run with sudo (Linux/Mac)
sudo docker-compose up -d

# Or add your user to docker group
sudo usermod -aG docker $USER
```

### Database Connection Issues
1. Make sure DB container is running: `docker-compose ps`
2. Check .env file has correct credentials
3. Wait a few seconds for MySQL to initialize
4. Check logs: `docker-compose logs db`

### Clear Everything and Start Fresh
```bash
docker-compose down -v
docker-compose up -d --build
```

## 📝 Notes

- Storage and bootstrap/cache folders are mounted as volumes
- Database data persists in Docker volume `dbdata`
- PHP-FPM runs on port 9000 (internal)
- Nginx serves on port 8000
- MySQL accessible on port 3306
- phpMyAdmin accessible on port 8080
