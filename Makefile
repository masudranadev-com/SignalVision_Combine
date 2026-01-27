# =============================================================================
# SignalVision Docker Makefile
# =============================================================================

.PHONY: help build up down restart logs shell migrate fresh seed cache-clear composer-install npm-install

# Default target
help:
	@echo "SignalVision Docker Commands"
	@echo "============================="
	@echo ""
	@echo "Setup Commands:"
	@echo "  make setup          - Initial setup (build, up, install deps, migrate)"
	@echo "  make build          - Build all Docker images"
	@echo "  make up             - Start all containers"
	@echo "  make down           - Stop all containers"
	@echo "  make restart        - Restart all containers"
	@echo ""
	@echo "Development Commands:"
	@echo "  make logs           - View logs from all containers"
	@echo "  make logs-f         - Follow logs from all containers"
	@echo "  make shell-admin    - Shell into Admin container"
	@echo "  make shell-manager  - Shell into Manager container"
	@echo "  make shell-trader   - Shell into Trader container"
	@echo "  make shell-mysql    - Shell into MySQL container"
	@echo "  make shell-redis    - Shell into Redis container"
	@echo ""
	@echo "Laravel Commands:"
	@echo "  make migrate        - Run migrations for all Laravel apps"
	@echo "  make fresh          - Fresh migrate with seed for all apps"
	@echo "  make seed           - Run seeders for all Laravel apps"
	@echo "  make cache-clear    - Clear cache for all Laravel apps"
	@echo "  make composer-install - Install composer deps for all apps"
	@echo ""
	@echo "Maintenance Commands:"
	@echo "  make prune          - Remove unused Docker resources"
	@echo "  make reset          - Full reset (down, prune, build, up)"

# Initial setup
setup: build up composer-install migrate
	@echo "Setup complete!"

# Build images
build:
	docker-compose build

# Start containers
up:
	docker-compose up -d

# Stop containers
down:
	docker-compose down

# Restart containers
restart: down up

# View logs
logs:
	docker-compose logs

logs-f:
	docker-compose logs -f

# Shell access
shell-admin:
	docker-compose exec admin-app sh

shell-manager:
	docker-compose exec manager-app sh

shell-trader:
	docker-compose exec trader-app sh

shell-mysql:
	docker-compose exec mysql mysql -u root -p

shell-redis:
	docker-compose exec redis redis-cli

# Laravel migrations
migrate:
	docker-compose exec admin-app php artisan migrate --force
	docker-compose exec manager-app php artisan migrate --force
	docker-compose exec trader-app php artisan migrate --force

# Fresh migrate with seed
fresh:
	docker-compose exec admin-app php artisan migrate:fresh --seed --force
	docker-compose exec manager-app php artisan migrate:fresh --seed --force
	docker-compose exec trader-app php artisan migrate:fresh --seed --force

# Run seeders
seed:
	docker-compose exec admin-app php artisan db:seed --force
	docker-compose exec manager-app php artisan db:seed --force
	docker-compose exec trader-app php artisan db:seed --force

# Clear cache
cache-clear:
	docker-compose exec admin-app php artisan cache:clear
	docker-compose exec admin-app php artisan config:clear
	docker-compose exec admin-app php artisan view:clear
	docker-compose exec admin-app php artisan route:clear
	docker-compose exec manager-app php artisan cache:clear
	docker-compose exec manager-app php artisan config:clear
	docker-compose exec manager-app php artisan view:clear
	docker-compose exec manager-app php artisan route:clear
	docker-compose exec trader-app php artisan cache:clear
	docker-compose exec trader-app php artisan config:clear
	docker-compose exec trader-app php artisan view:clear
	docker-compose exec trader-app php artisan route:clear

# Optimize for production
optimize:
	docker-compose exec admin-app php artisan config:cache
	docker-compose exec admin-app php artisan route:cache
	docker-compose exec admin-app php artisan view:cache
	docker-compose exec manager-app php artisan config:cache
	docker-compose exec manager-app php artisan route:cache
	docker-compose exec manager-app php artisan view:cache
	docker-compose exec manager-app php artisan filament:cache-components
	docker-compose exec trader-app php artisan config:cache
	docker-compose exec trader-app php artisan route:cache
	docker-compose exec trader-app php artisan view:cache

# Install composer dependencies
composer-install:
	docker-compose exec admin-app composer install --no-dev --optimize-autoloader
	docker-compose exec manager-app composer install --no-dev --optimize-autoloader
	docker-compose exec trader-app composer install --no-dev --optimize-autoloader

# Install npm dependencies and build
npm-build:
	docker-compose exec manager-app npm install && npm run build

# Prune unused resources
prune:
	docker system prune -f
	docker volume prune -f

# Full reset
reset: down prune build up composer-install migrate
	@echo "Reset complete!"

# Horizon status
horizon-status:
	docker-compose exec manager-app php artisan horizon:status
	docker-compose exec trader-app php artisan horizon:status

# Restart Horizon workers
horizon-restart:
	docker-compose exec manager-app php artisan horizon:terminate
	docker-compose exec trader-app php artisan horizon:terminate
