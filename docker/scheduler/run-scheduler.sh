#!/bin/sh
# =============================================================================
# Laravel Scheduler Runner
# Runs scheduler for all Laravel applications every minute
# =============================================================================

echo "Starting Laravel Scheduler for all applications..."

while true; do
    # Admin Scheduler
    if [ -f /var/www/admin/artisan ]; then
        cd /var/www/admin && php artisan schedule:run >> /var/log/scheduler/admin.log 2>&1
    fi

    # Manager Scheduler
    if [ -f /var/www/manager/artisan ]; then
        cd /var/www/manager && php artisan schedule:run >> /var/log/scheduler/manager.log 2>&1
    fi

    # Trader Scheduler
    if [ -f /var/www/trader/artisan ]; then
        cd /var/www/trader && php artisan schedule:run >> /var/log/scheduler/trader.log 2>&1
    fi

    # Sleep for 60 seconds
    sleep 60
done
