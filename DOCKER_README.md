# SignalVision Docker Setup

## Overview

This Docker setup includes all 5 applications:

| Service | Type | Port | Database |
|---------|------|------|----------|
| Admin | Laravel 10 | 80 (via nginx) | MySQL `admin` |
| Manager | Laravel 10 | 80 (via nginx) | MySQL `manager` |
| Trader | Laravel 10 | 80 (via nginx) | MySQL `trader` |
| WordPress | PHP | 80 (via nginx) | MySQL `signalvision` |
| NotifyGo | Go 1.21 | 8000 | None |

## Services Architecture

```
                                    ┌─────────────────┐
                                    │     Nginx       │
                                    │   (Port 80)     │
                                    └────────┬────────┘
                                             │
           ┌─────────────┬─────────────┬─────┴─────┬─────────────┐
           │             │             │           │             │
           ▼             ▼             ▼           ▼             ▼
    ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌─────────┐ ┌─────────┐
    │   Admin    │ │  Manager   │ │   Trader   │ │WordPress│ │NotifyGo │
    │  (PHP-FPM) │ │  (PHP-FPM) │ │  (PHP-FPM) │ │(PHP-FPM)│ │  (Go)   │
    └─────┬──────┘ └─────┬──────┘ └─────┬──────┘ └────┬────┘ └─────────┘
          │              │              │             │
          │              │              │             │
          ▼              ▼              ▼             ▼
    ┌──────────────────────────────────────────────────────┐
    │                      MySQL 8.0                        │
    │  Databases: admin, manager, trader, signalvision     │
    └──────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┴───────────────────┐
          │                                       │
          ▼                                       ▼
    ┌────────────┐                         ┌────────────┐
    │   Redis    │◄────────────────────────│  Horizon   │
    │   (Cache,  │                         │  Workers   │
    │   Queue)   │                         │            │
    └────────────┘                         └────────────┘
```

## Quick Start

### 1. Copy Environment File
```bash
cp .env.docker .env
```

### 2. Update Environment Variables
Edit `.env` and set secure passwords:
```env
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_PASSWORD=your_secure_app_password
```

### 3. Build and Start
```bash
# Using Make (recommended)
make setup

# Or manually
docker-compose build
docker-compose up -d
```

### 4. Run Migrations
```bash
make migrate
```

## Available Commands

### Using Makefile
```bash
make help              # Show all commands
make build             # Build images
make up                # Start containers
make down              # Stop containers
make restart           # Restart containers
make logs-f            # Follow logs
make migrate           # Run migrations
make cache-clear       # Clear all caches
make shell-admin       # Access Admin container
make shell-manager     # Access Manager container
make shell-trader      # Access Trader container
```

### Manual Docker Commands
```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Execute command in container
docker-compose exec admin-app php artisan migrate
docker-compose exec manager-app php artisan horizon
```

## Domains Configuration

For local development, add these to your hosts file:

```
# Windows: C:\Windows\System32\drivers\etc\hosts
# Linux/Mac: /etc/hosts

127.0.0.1 admin.signalvision.local
127.0.0.1 manager.signalvision.local
127.0.0.1 trader.signalvision.local
127.0.0.1 signalvision.local
127.0.0.1 node.signalvision.local
```

## Environment Configuration

### Laravel Apps (.env)
Update each Laravel app's `.env` file with Docker-specific settings:

```env
DB_HOST=mysql
REDIS_HOST=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### WordPress (wp-config.php)
Update database connection:
```php
define('DB_HOST', 'mysql');
```

## Ports Mapping

| Service | Internal Port | External Port |
|---------|--------------|---------------|
| Nginx | 80 | 80 |
| Nginx SSL | 443 | 443 |
| MySQL | 3306 | 3306 |
| Redis | 6379 | 6379 |
| NotifyGo | 8000 | 8000 |
| Reverb (WebSocket) | 8080 | 8080 |

## Volumes

Persistent data is stored in Docker volumes:
- `mysql_data` - MySQL database files
- `redis_data` - Redis persistence

## Production Deployment

### 1. SSL Configuration
Place your SSL certificates in `docker/nginx/ssl/`:
- `certificate.crt`
- `private.key`

### 2. Update Nginx Config
Enable HTTPS in nginx conf files.

### 3. Security Checklist
- [ ] Change all default passwords
- [ ] Generate new Laravel APP_KEYs
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper CORS settings
- [ ] Enable SSL/TLS
- [ ] Set up firewall rules
- [ ] Configure backup strategy

### 4. Optimize for Production
```bash
make optimize
```

## Troubleshooting

### Container won't start
```bash
docker-compose logs <service-name>
```

### Permission issues
```bash
docker-compose exec admin-app chown -R www:www /var/www/admin/storage
docker-compose exec admin-app chmod -R 775 /var/www/admin/storage
```

### Database connection refused
Wait for MySQL to be ready:
```bash
docker-compose exec mysql mysqladmin ping -h localhost
```

### Clear all and rebuild
```bash
make reset
```

## Backup & Restore

### Database Backup
```bash
docker-compose exec mysql mysqldump -u root -p --all-databases > backup.sql
```

### Database Restore
```bash
docker-compose exec -T mysql mysql -u root -p < backup.sql
```

## Health Checks

All services include health checks:
```bash
# Check all services status
docker-compose ps

# Check specific service health
docker inspect --format='{{.State.Health.Status}}' signalvision_mysql
```
