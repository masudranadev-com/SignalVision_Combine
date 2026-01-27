#!/bin/bash

# SignalNode Docker Deployment Script
# Usage: ./deploy.sh [start|stop|restart|logs|status|update]

set -e

COLOR_GREEN='\033[0;32m'
COLOR_BLUE='\033[0;34m'
COLOR_YELLOW='\033[1;33m'
COLOR_RED='\033[0;31m'
COLOR_RESET='\033[0m'

info() {
    echo -e "${COLOR_BLUE}[INFO]${COLOR_RESET} $1"
}

success() {
    echo -e "${COLOR_GREEN}[SUCCESS]${COLOR_RESET} $1"
}

warn() {
    echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} $1"
}

error() {
    echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $1"
}

check_docker() {
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi

    success "Docker and Docker Compose found"
}

start() {
    info "Starting SignalNode..."
    docker-compose up -d
    sleep 3
    status
    success "SignalNode started"
}

stop() {
    info "Stopping SignalNode..."
    docker-compose down
    success "SignalNode stopped"
}

restart() {
    info "Restarting SignalNode..."
    docker-compose restart
    sleep 3
    status
    success "SignalNode restarted"
}

logs() {
    info "Showing logs (Ctrl+C to exit)..."
    docker-compose logs -f
}

status() {
    info "Checking SignalNode status..."
    docker-compose ps

    echo ""
    if docker-compose ps | grep -q "Up"; then
        info "Testing health endpoint..."
        if curl -f -s http://localhost:8000/health > /dev/null; then
            success "Health check passed ✓"
        else
            warn "Health check failed ✗"
        fi

        info "Container stats:"
        docker stats --no-stream signalnode
    else
        warn "SignalNode is not running"
    fi
}

update() {
    info "Updating SignalNode..."
    docker-compose down
    docker-compose build --no-cache
    docker-compose up -d
    sleep 3
    status
    success "SignalNode updated"
}

build() {
    info "Building SignalNode image..."
    docker-compose build
    success "Build complete"
}

clean() {
    warn "This will remove all containers and images. Continue? (y/N)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        info "Cleaning up..."
        docker-compose down -v
        docker system prune -f
        success "Cleanup complete"
    else
        info "Cleanup cancelled"
    fi
}

shell() {
    info "Opening shell in SignalNode container..."
    docker exec -it signalnode sh
}

case "$1" in
    start)
        check_docker
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    logs)
        logs
        ;;
    status)
        status
        ;;
    update)
        check_docker
        update
        ;;
    build)
        check_docker
        build
        ;;
    clean)
        clean
        ;;
    shell)
        shell
        ;;
    *)
        echo "SignalNode Docker Management"
        echo ""
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  start    - Start SignalNode container"
        echo "  stop     - Stop SignalNode container"
        echo "  restart  - Restart SignalNode container"
        echo "  logs     - View container logs (live)"
        echo "  status   - Show container status and health"
        echo "  update   - Rebuild and restart container"
        echo "  build    - Build Docker image"
        echo "  clean    - Remove containers and clean up"
        echo "  shell    - Open shell in container"
        echo ""
        exit 1
        ;;
esac
