@echo off
REM =============================================================================
REM SignalVision Docker Startup Script for Windows
REM =============================================================================

echo ==========================================
echo SignalVision Docker Setup
echo ==========================================

REM Check if .env exists
if not exist ".env" (
    echo Creating .env from .env.docker...
    copy .env.docker .env
    echo Please edit .env with your secure passwords, then run this script again.
    pause
    exit /b 1
)

REM Build images
echo.
echo Building Docker images...
docker-compose build

REM Start services
echo.
echo Starting services...
docker-compose up -d

REM Wait for MySQL to be ready
echo.
echo Waiting for MySQL to be ready...
:wait_mysql
docker-compose exec -T mysql mysqladmin ping -h localhost -u root --silent >nul 2>&1
if errorlevel 1 (
    echo Waiting for MySQL...
    timeout /t 5 /nobreak >nul
    goto wait_mysql
)
echo MySQL is ready!

REM Run composer install for each Laravel app
echo.
echo Installing Composer dependencies...
docker-compose exec -T admin-app composer install --no-dev --optimize-autoloader
docker-compose exec -T manager-app composer install --no-dev --optimize-autoloader
docker-compose exec -T trader-app composer install --no-dev --optimize-autoloader

REM Run migrations
echo.
echo Running database migrations...
docker-compose exec -T admin-app php artisan migrate --force
docker-compose exec -T manager-app php artisan migrate --force
docker-compose exec -T trader-app php artisan migrate --force

REM Set permissions
echo.
echo Setting storage permissions...
docker-compose exec -T admin-app chmod -R 775 storage bootstrap/cache
docker-compose exec -T manager-app chmod -R 775 storage bootstrap/cache
docker-compose exec -T trader-app chmod -R 775 storage bootstrap/cache

REM Clear and cache config
echo.
echo Optimizing for production...
docker-compose exec -T admin-app php artisan config:cache
docker-compose exec -T admin-app php artisan route:cache
docker-compose exec -T admin-app php artisan view:cache

docker-compose exec -T manager-app php artisan config:cache
docker-compose exec -T manager-app php artisan route:cache
docker-compose exec -T manager-app php artisan view:cache

docker-compose exec -T trader-app php artisan config:cache
docker-compose exec -T trader-app php artisan route:cache
docker-compose exec -T trader-app php artisan view:cache

echo.
echo ==========================================
echo Setup Complete!
echo ==========================================
echo.
echo Services running at:
echo   - Admin:      http://admin.signalvision.local
echo   - Manager:    http://manager.signalvision.local
echo   - Trader:     http://trader.signalvision.local
echo   - WordPress:  http://signalvision.local
echo   - NotifyGo:   http://node.signalvision.local
echo.
echo Development tools (if override enabled):
echo   - PHPMyAdmin:     http://localhost:8081
echo   - Redis Commander: http://localhost:8082
echo   - Mailpit:        http://localhost:8025
echo.
echo Don't forget to add entries to your hosts file!
echo.
pause
