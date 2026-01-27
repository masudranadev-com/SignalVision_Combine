#!/bin/bash
# =============================================================================
# SignalVision Docker Startup Script
# =============================================================================

set -e

echo "=========================================="
echo "SignalVision Docker Setup"
echo "=========================================="

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "Creating .env from .env.docker..."
    cp .env.docker .env
    echo "Please edit .env with your secure passwords, then run this script again."
    exit 1
fi

# Check Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running. Please start Docker first."
    exit 1
fi

# Build images
echo ""
echo "Building Docker images..."
docker-compose build

# Start services
echo ""
echo "Starting services..."
docker-compose up -d

# Wait for MySQL to be ready
echo ""
echo "Waiting for MySQL to be ready..."
until docker-compose exec -T mysql mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD:-rootpassword}" --silent; do
    echo "Waiting for MySQL..."
    sleep 5
done
echo "MySQL is ready!"

# Run composer install for each Laravel app
echo ""
echo "Installing Composer dependencies..."
docker-compose exec -T admin-app composer install --no-dev --optimize-autoloader
docker-compose exec -T manager-app composer install --no-dev --optimize-autoloader
docker-compose exec -T trader-app composer install --no-dev --optimize-autoloader

# Run migrations
echo ""
echo "Running database migrations..."
docker-compose exec -T admin-app php artisan migrate --force
docker-compose exec -T manager-app php artisan migrate --force
docker-compose exec -T trader-app php artisan migrate --force

# Set permissions
echo ""
echo "Setting storage permissions..."
docker-compose exec -T admin-app chmod -R 775 storage bootstrap/cache
docker-compose exec -T manager-app chmod -R 775 storage bootstrap/cache
docker-compose exec -T trader-app chmod -R 775 storage bootstrap/cache

# Clear and cache config
echo ""
echo "Optimizing for production..."
docker-compose exec -T admin-app php artisan config:cache
docker-compose exec -T admin-app php artisan route:cache
docker-compose exec -T admin-app php artisan view:cache

docker-compose exec -T manager-app php artisan config:cache
docker-compose exec -T manager-app php artisan route:cache
docker-compose exec -T manager-app php artisan view:cache

docker-compose exec -T trader-app php artisan config:cache
docker-compose exec -T trader-app php artisan route:cache
docker-compose exec -T trader-app php artisan view:cache

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Services running at:"
echo "  - Admin:      http://admin.signalvision.local"
echo "  - Manager:    http://manager.signalvision.local"
echo "  - Trader:     http://trader.signalvision.local"
echo "  - WordPress:  http://signalvision.local"
echo "  - NotifyGo:   http://node.signalvision.local"
echo ""
echo "Development tools (if override enabled):"
echo "  - PHPMyAdmin:     http://localhost:8081"
echo "  - Redis Commander: http://localhost:8082"
echo "  - Mailpit:        http://localhost:8025"
echo ""
echo "Don't forget to add entries to your hosts file!"
echo ""
