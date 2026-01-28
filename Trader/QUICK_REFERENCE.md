# Signal Vision Trader - Quick Reference Guide

## Project Rating: 52/100 - NOT PRODUCTION READY

---

## Critical Issues Summary

### URGENT - Must Fix Before Production:

1. **CRITICAL: All API Routes Unprotected**
   - File: `routes/api.php` lines 24 and 124
   - Issue: Authentication middleware is commented out
   - Risk: Anyone can execute trades, access data, delete users
   - Fix: Uncomment the middleware wrapper

2. **CRITICAL: Dangerous Database Delete Endpoint**
   - File: `routes/api.php` lines 110-123
   - Issue: Public endpoint can truncate telegram_users table
   - Risk: Complete data loss
   - Fix: Remove or protect with authentication

3. **HIGH: Debug Mode Enabled**
   - File: `.env` line 4
   - Issue: `APP_DEBUG=true` exposes sensitive information
   - Fix: Set `APP_DEBUG=false`

4. **HIGH: Wrong Environment**
   - File: `.env` line 2
   - Issue: `APP_ENV=local` instead of production
   - Fix: Set `APP_ENV=production`

5. **HIGH: Open CORS Policy**
   - File: `config/cors.php`
   - Issue: Allows all origins (`*`)
   - Fix: Restrict to specific domains

---

## Quick Fixes

### 1. Enable API Authentication
```php
// routes/api.php
// Uncomment line 24:
Route::middleware('crypto.api.secret')->group(function () {

// Uncomment line 124:
});
```

### 2. Remove Dangerous Endpoint
```php
// routes/api.php
// DELETE or protect lines 110-123:
if (env('APP_ENV') !== 'production') {
    Route::get('clear-test-data', function () {
        // ... existing code
    })->middleware('auth:api');
}
```

### 3. Update Environment
```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

### 4. Restrict CORS
```php
// config/cors.php
'allowed_origins' => [
    'https://manager.signalvision.ai',
    'https://trader.signalvision.ai'
],
```

### 5. Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

---

## Project Score Breakdown

| Category | Score | Status |
|----------|-------|--------|
| Security | 25/100 | CRITICAL FAILURE |
| Environment Config | 40/100 | NEEDS WORK |
| Code Quality | 60/100 | ACCEPTABLE |
| Dependencies | 70/100 | GOOD |
| Database | 65/100 | ACCEPTABLE |
| API Security | 20/100 | CRITICAL FAILURE |
| Error Handling | 60/100 | ACCEPTABLE |
| Performance | 55/100 | NEEDS WORK |
| Infrastructure | 45/100 | NEEDS WORK |
| Documentation | 30/100 | NEEDS WORK |
| **OVERALL** | **52/100** | **NOT READY** |

---

## What is This Project?

**Signal Vision Trader** is a Laravel-based cryptocurrency trading bot platform:
- Telegram bot interface for user interaction
- Integrates with Bybit and Binance exchanges
- Automated trade execution based on signals
- Money management and risk controls
- License-based subscription system
- Multi-user support

**Tech Stack:**
- PHP 8.1 + Laravel 10
- MySQL database
- Redis for queues/cache
- Telegram Bot SDK
- Binance/Bybit API integration

**Code Stats:**
- 37 PHP files in app directory
- 7,663 total lines of code
- Largest file: TelegramBotController (4,029 lines)

---

## Common Commands

### Development
```bash
# Start development server
php artisan serve

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Open tinker (REPL)
php artisan tinker

# Run tests
php artisan test

# Format code
./vendor/bin/pint
```

### Production
```bash
# Maintenance mode ON
php artisan down

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart signal-trader-worker:*

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Maintenance mode OFF
php artisan up
```

### Queue Management
```bash
# Check queue workers
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart signal-trader-worker:*

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Monitor queue (Horizon)
# Visit: https://trader.signalvision.ai/horizon
```

### Debugging
```bash
# View logs
tail -f storage/logs/laravel.log

# Check application info
php artisan about

# Check routes
php artisan route:list

# Check database connection
php artisan tinker
>>> \DB::connection()->getPdo();
```

---

## Environment Variables Reference

### Critical Settings
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://trader.signalvision.ai

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_DATABASE=trader
DB_USERNAME=trader
DB_PASSWORD=...

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

TELEGRAM_BOT_TOKEN=...
API_SECRET=...
```

---

## API Endpoints Quick Reference

All endpoints require `API-SECRET` header!

### Bybit
- POST `/api/bybit/wallet` - Get balance
- POST `/api/bybit/orders/smart` - Smart order
- POST `/api/bybit/orders/market` - Market order
- POST `/api/bybit/update/tp-sl` - Update TP/SL
- POST `/api/bybit/order/close` - Close order

### Binance
- POST `/api/binance/wallet-balance` - Get balance
- POST `/api/binance/open-trade` - Place order
- POST `/api/binance/market-entry` - Market entry
- POST `/api/binance/close-position` - Close position
- POST `/api/binance/update-trade-tp-sl` - Update TP/SL

### License
- POST `/api/license/validation` - Validate license
- POST `/api/license/status` - Check status

### Money Management
- POST `/api/money-management/info` - Get config
- POST `/api/money-management/update-config` - Update config

### Admin
- POST `/api/admin/users` - List users

---

## Database Tables

1. **telegram_users** - Main user data
   - chat_id, state, subscription_type
   - api_key, api_secret (NOT encrypted - security issue)
   - activation_in, expired_in

2. **subscriptions** - License tracking
   - user_id, license, product_id
   - start_date, next_date, package_type

3. **users** - Admin accounts
4. **personal_access_tokens** - API tokens
5. **failed_jobs** - Failed queue jobs

---

## Security Checklist

- [ ] API authentication enabled (routes/api.php)
- [ ] Dangerous test endpoint removed/protected
- [ ] APP_DEBUG=false
- [ ] APP_ENV=production
- [ ] CORS restricted to specific domains
- [ ] API keys encrypted in database
- [ ] Rate limiting enabled
- [ ] SSL/HTTPS configured
- [ ] Input validation on all endpoints
- [ ] Error tracking configured (Sentry)

---

## Infrastructure Checklist

- [ ] SSL certificate installed
- [ ] Nginx/Apache configured correctly
- [ ] PHP-FPM running
- [ ] MySQL database configured
- [ ] Redis server running
- [ ] Queue workers running (Supervisor)
- [ ] Telegram webhook configured
- [ ] Database backups scheduled
- [ ] Monitoring configured
- [ ] Log rotation configured

---

## Troubleshooting Quick Fixes

### Bot Not Responding
```bash
# Reset webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://trader.signalvision.ai/telegram-message-webhook"

# Restart queue workers
sudo supervisorctl restart signal-trader-worker:*
```

### API Errors
```bash
# Check middleware is enabled in routes/api.php
# Verify API secret matches
cat .env | grep API_SECRET
```

### Queue Jobs Stuck
```bash
# Restart workers
sudo supervisorctl restart signal-trader-worker:*

# Retry failed jobs
php artisan queue:retry all
```

### Database Issues
```bash
# Check MySQL running
sudo systemctl status mysql

# Test connection
mysql -u trader -p
```

---

## Key Files Location

- Routes: `routes/api.php`, `routes/web.php`
- Controllers: `app/Http/Controllers/`
- Models: `app/Models/`
- Services: `app/Services/`
- Config: `config/`
- Migrations: `database/migrations/`
- Environment: `.env`
- Logs: `storage/logs/laravel.log`

---

## Important URLs

- Application: https://trader.signalvision.ai
- Horizon (Queue): https://trader.signalvision.ai/horizon
- Licensing Server: https://manager.signalvision.ai
- Webhook: https://trader.signalvision.ai/telegram-message-webhook

---

## Next Steps to Production

### Phase 1: Critical Fixes (1-2 days)
1. Enable API authentication
2. Remove dangerous endpoint
3. Update environment config
4. Restrict CORS

### Phase 2: Security (2-3 days)
5. Encrypt API keys in database
6. Add rate limiting
7. Implement input validation
8. Configure SSL/HTTPS

### Phase 3: Infrastructure (2-3 days)
9. Configure queue workers with Supervisor
10. Set up error tracking (Sentry)
11. Configure database backups
12. Set up monitoring

### Phase 4: Testing (2-3 days)
13. Write unit tests
14. Integration testing
15. Security testing
16. Load testing

**Total Timeline: 8-13 days**

---

## Support

- Full Documentation: `PROJECT_DOCUMENTATION.md`
- Production Readiness Report: `PRODUCTION_READINESS_REPORT.md`
- Laravel Docs: https://laravel.com/docs/10.x
- Telegram Bot API: https://core.telegram.org/bots/api

---

**Last Updated:** December 30, 2025
**Production Ready:** NO (52/100)
**Critical Issues:** 5
**High Priority Issues:** 9
**Estimated Time to Production:** 8-13 days
