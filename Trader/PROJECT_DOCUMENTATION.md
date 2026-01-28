# Signal Vision Trader - Project Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Technology Stack](#technology-stack)
4. [Features](#features)
5. [Database Schema](#database-schema)
6. [API Reference](#api-reference)
7. [Installation & Setup](#installation--setup)
8. [Configuration](#configuration)
9. [Deployment](#deployment)
10. [Telegram Bot Usage](#telegram-bot-usage)
11. [Troubleshooting](#troubleshooting)
12. [Development Guide](#development-guide)

---

## Project Overview

### What is Signal Vision Trader?

Signal Vision Trader is a Laravel-based cryptocurrency trading automation platform that enables users to execute trades on major cryptocurrency exchanges (Bybit and Binance) through an intuitive Telegram bot interface. The platform provides comprehensive money management, license-based access control, and real-time trading capabilities.

### Key Capabilities:
- Automated cryptocurrency trading on Bybit and Binance
- Telegram bot interface for trade management
- Smart order placement with risk management
- License-based subscription system
- Multi-user support with individual configurations
- Real-time position monitoring and management
- Demo and live trading modes

### Target Users:
- Cryptocurrency traders seeking automation
- Trading signal providers
- Users wanting to execute trades via Telegram
- Trading groups requiring multi-user management

---

## System Architecture

### High-Level Architecture

```
┌─────────────────┐
│  Telegram Bot   │
│   (User UI)     │
└────────┬────────┘
         │
         │ Webhook
         ▼
┌─────────────────────────────────────────┐
│         Laravel Application             │
│  ┌─────────────────────────────────┐   │
│  │   TelegramBotController         │   │
│  │   (Message Processing)          │   │
│  └──────────┬──────────────────────┘   │
│             │                           │
│             ▼                           │
│  ┌─────────────────────────────────┐   │
│  │   Business Logic Controllers    │   │
│  │  • CryptoApiBybit               │   │
│  │  • CryptoApiBinance             │   │
│  │  • MoneyManagementController    │   │
│  │  • SignalTraderLicense          │   │
│  └──────────┬──────────────────────┘   │
│             │                           │
│             ▼                           │
│  ┌─────────────────────────────────┐   │
│  │     Services Layer              │   │
│  │  • BinanceService               │   │
│  └──────────┬──────────────────────┘   │
│             │                           │
│             ▼                           │
│  ┌─────────────────────────────────┐   │
│  │    Database (MySQL)             │   │
│  │  • telegram_users               │   │
│  │  • subscriptions                │   │
│  │  • users                        │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │    Queue System (Redis)         │   │
│  │  • WebhooksJob                  │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
         │              │
         │              │
         ▼              ▼
┌─────────────┐  ┌─────────────┐
│   Bybit     │  │   Binance   │
│  Exchange   │  │  Exchange   │
└─────────────┘  └─────────────┘
```

### Component Breakdown

#### 1. Telegram Bot Layer
- **Purpose:** User interaction interface
- **Functionality:**
  - Receives user commands
  - Presents interactive menus
  - Displays trade confirmations
  - Sends notifications
- **Technology:** Telegram Bot SDK

#### 2. Application Layer
- **Purpose:** Core business logic processing
- **Components:**
  - Controllers: Handle HTTP requests and responses
  - Services: Encapsulate business logic
  - Jobs: Process background tasks
  - Middleware: Request filtering and authentication

#### 3. Data Layer
- **Purpose:** Data persistence and retrieval
- **Components:**
  - MySQL: Primary database
  - Redis: Cache and queue storage
  - Eloquent ORM: Database abstraction

#### 4. External Integrations
- **Bybit Exchange:** Cryptocurrency trading API
- **Binance Exchange:** Cryptocurrency trading API
- **SignalVision Licensing:** License validation service

---

## Technology Stack

### Backend Framework
- **PHP 8.1+**
- **Laravel 10.48** - PHP web application framework
  - MVC architecture
  - Eloquent ORM
  - Queue system
  - Event broadcasting
  - Middleware support

### Database & Caching
- **MySQL** - Primary relational database
- **Redis** - Queue management and caching

### External Libraries
| Library | Version | Purpose |
|---------|---------|---------|
| telegram-bot-sdk | 3.15.0 | Telegram Bot API integration |
| binance-connector-php | 2.0.1 | Official Binance API client |
| php-binance-api | 0.5.30 | Alternate Binance API implementation |
| guzzlehttp/guzzle | 7.9.3 | HTTP client for API calls |
| laravel/horizon | 5.40.0 | Redis queue monitoring dashboard |
| laravel/sanctum | 3.3.3 | API token authentication |

### Development Tools
- **Laravel Pint** - Code formatting
- **PHPUnit** - Testing framework
- **Laravel Sail** - Docker development environment
- **Vite** - Frontend build tool

### Infrastructure
- **Web Server:** Nginx/Apache
- **PHP-FPM:** PHP process manager
- **Supervisor:** Process control for queue workers
- **SSL/TLS:** HTTPS encryption

---

## Features

### 1. Trading Operations

#### Supported Exchanges:
- **Bybit** (Futures Trading)
  - Smart order placement
  - Market orders
  - Partial order execution
  - Position management
  - TP/SL updates

- **Binance** (Futures Trading)
  - Limit orders
  - Market entry
  - Position status checking
  - Partial trade opening
  - Order cancellation

#### Order Types:
- Smart Orders: Automatically calculated quantities based on risk management
- Market Orders: Instant execution at current price
- Limit Orders: Execution at specified price
- Partial Orders: Split entries across multiple price points

#### Position Management:
- Open positions
- Close positions (full or partial)
- Update Take Profit (TP)
- Update Stop Loss (SL)
- View position lists
- Real-time position status

### 2. Money Management System

#### Risk Parameters:
- **Risk Percentage:** Percentage of balance to risk per trade
- **Maximum Exposure:** Maximum simultaneous open positions
- **Daily Loss Limit:** Stop trading after reaching daily loss threshold
- **Trade Limits:** Maximum number of trades per day
- **Stop Trading Trigger:** Automatic trading halt conditions

#### Balance Tracking:
- **Demo Balance:** Virtual balance for testing
- **Live Balance:** Actual exchange account balance
- **Wallet Balance:** Real-time balance from exchange
- **Balance History:** Track balance changes over time

#### Strategy Configuration:
- **Universal Strategy:** Apply same settings across all symbols
- **Leverage Management:** Configure leverage per trade
- **Mode Selection:** Switch between Demo and Live trading

### 3. License Management

#### License Features:
- License validation via external API
- Subscription type tracking (different tiers/packages)
- Activation and expiration date management
- Product-specific licensing (SignalShot vs SignalManager)
- License status checking
- Automatic expiration handling

#### Subscription Types:
- Individual user licenses
- Package-based pricing
- Renewable subscriptions
- Grace period handling

### 4. Telegram Bot Interface

#### User Interaction Flow:
1. User sends command to bot
2. Bot validates license status
3. Bot presents menu options
4. User selects action (configure API, place trade, etc.)
5. Bot processes request
6. Bot sends confirmation/result

#### State Management:
- Conversation state tracking
- Context preservation across messages
- Multi-step input collection
- Error recovery

#### Interactive Features:
- Inline keyboards for menu navigation
- Callback queries for button actions
- Text input validation
- Real-time notifications
- Trade confirmations

### 5. Admin & Support Features

#### Admin Panel:
- User management dashboard
- Paginated user listing
- Search functionality
- View user configurations
- License tracking
- Subscription management

#### Support Bot:
- User information retrieval
- License activation assistance
- Troubleshooting support
- Account status checking

---

## Database Schema

### Tables Overview

#### 1. `telegram_users`
Primary user table storing Telegram user configurations.

```sql
CREATE TABLE telegram_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NULL,
    state VARCHAR(255) NULL,
    subscription_type VARCHAR(255) NULL,
    activation_in VARCHAR(255) NULL,
    expired_in VARCHAR(255) NULL,
    api_key VARCHAR(255) NULL,
    api_secret VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Key Fields:**
- `chat_id`: Unique Telegram user identifier
- `state`: Current conversation state (for multi-step interactions)
- `subscription_type`: User's subscription tier
- `activation_in`: License activation date
- `expired_in`: License expiration date
- `api_key`: Exchange API key (should be encrypted)
- `api_secret`: Exchange API secret (should be encrypted)

#### 2. `subscriptions`
License and subscription tracking.

```sql
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NULL,
    license VARCHAR(255) NULL,
    name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    product_id VARCHAR(255) NULL,
    start_date TIMESTAMP NULL,
    next_date TIMESTAMP NULL,
    package_type VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Key Fields:**
- `user_id`: Reference to user
- `license`: Unique license key
- `product_id`: Product identifier (SignalShot/SignalManager)
- `package_type`: Subscription package tier
- `start_date`: Subscription start
- `next_date`: Next renewal/expiration date

#### 3. `users`
Standard Laravel users table for admin authentication.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 4. `personal_access_tokens`
Laravel Sanctum API tokens for authentication.

```sql
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 5. `failed_jobs`
Failed queue job tracking for debugging.

```sql
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Relationships

```
telegram_users
    └── No direct foreign keys currently implemented

subscriptions
    └── user_id → references users indirectly

users (admin)
    └── Has many personal_access_tokens
```

**Recommended Improvements:**
- Add foreign key constraints
- Add indexes on frequently queried columns (chat_id, user_id, license)
- Implement soft deletes for data retention
- Add audit trail tables for sensitive operations

---

## API Reference

### Base URL
```
https://trader.signalvision.ai/api
```

### Authentication
All API endpoints should use the `crypto.api.secret` middleware:

**Header:**
```
API-SECRET: <your-api-secret-from-env>
```

---

### Bybit Endpoints

#### 1. Get Wallet Balance
```http
POST /api/bybit/wallet
```

**Request Body:**
```json
{
    "user_id": 12345
}
```

**Response:**
```json
{
    "status": true,
    "msg": "Balance retrieved successfully",
    "available_balance": "1000.50"
}
```

---

#### 2. Smart Order Placement
```http
POST /api/bybit/orders/smart
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT",
    "type": "Buy",
    "entryPrice": 50000,
    "stopLoss": 49000,
    "takeProfit": 52000,
    "leverage": 10
}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "orderId": "abc123",
        "symbol": "BTCUSDT",
        "side": "Buy",
        "quantity": "0.02",
        "price": "50000"
    }
}
```

---

#### 3. Place Market Order
```http
POST /api/bybit/orders/market
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "ETHUSDT",
    "type": "Sell",
    "qty": "1.5"
}
```

---

#### 4. Update Take Profit / Stop Loss
```http
POST /api/bybit/update/tp-sl
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT",
    "takeProfit": 53000,
    "stopLoss": 48500
}
```

---

#### 5. Close Order
```http
POST /api/bybit/order/close
```

**Request Body:**
```json
{
    "user_id": 12345,
    "orderId": "abc123"
}
```

---

#### 6. Get Position Lists
```http
POST /api/bybit/positions-order/lists/{user_id}
```

**Path Parameters:**
- `user_id`: Telegram user ID

**Response:**
```json
{
    "status": true,
    "positions": [
        {
            "symbol": "BTCUSDT",
            "side": "Buy",
            "size": "0.02",
            "entryPrice": "50000",
            "markPrice": "50500",
            "unrealizedPnl": "10.00"
        }
    ]
}
```

---

### Binance Endpoints

#### 1. Get Wallet Balance
```http
POST /api/binance/wallet-balance
```

**Request Body:**
```json
{
    "user_id": 12345
}
```

---

#### 2. Place Order
```http
POST /api/binance/open-trade
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT",
    "qty": "0.01",
    "entryPrice": 50000,
    "stopLoss": 49000,
    "takeProfit": 52000,
    "leverage": 10,
    "type": "BUY"
}
```

---

#### 3. Market Entry
```http
POST /api/binance/market-entry
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "ETHUSDT",
    "qty": "1.0",
    "type": "SELL"
}
```

---

#### 4. Check Position Status
```http
POST /api/binance/position-status
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT"
}
```

---

#### 5. Close Position
```http
POST /api/binance/close-position
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT"
}
```

---

#### 6. Update TP/SL
```http
POST /api/binance/update-trade-tp-sl
```

**Request Body:**
```json
{
    "user_id": 12345,
    "symbol": "BTCUSDT",
    "takeProfit": 53000,
    "stopLoss": 48500
}
```

---

### License Endpoints

#### 1. Validate License
```http
POST /api/license/validation
```

**Request Body:**
```json
{
    "license": "ABC-123-XYZ",
    "product_id": "1021"
}
```

**Response:**
```json
{
    "status": true,
    "license": "ABC-123-XYZ",
    "activation_date": "2025-01-01",
    "expiration_date": "2026-01-01",
    "package_type": "Premium"
}
```

---

#### 2. Check License Status
```http
POST /api/license/status
```

**Request Body:**
```json
{
    "user_id": 12345
}
```

---

### Money Management Endpoints

#### 1. Get Money Management Info
```http
POST /api/money-management/info
```

**Request Body:**
```json
{
    "user_id": 12345
}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "risk_percentage": 2.0,
        "max_exposure": 5,
        "daily_loss_limit": 100,
        "trade_limits": 10,
        "mode": "live",
        "demo_balance": 10000
    }
}
```

---

#### 2. Update Configuration
```http
POST /api/money-management/update-config
```

**Request Body:**
```json
{
    "user_id": 12345,
    "risk_percentage": 2.5,
    "max_exposure": 3,
    "daily_loss_limit": 150
}
```

---

#### 3. Update Demo Balance
```http
POST /api/money-management/demo-balance-update
```

**Request Body:**
```json
{
    "user_id": 12345,
    "balance": 15000
}
```

---

### Admin Endpoints

#### 1. Get Users List
```http
POST /api/admin/users
```

**Request Body:**
```json
{
    "page": 1,
    "per_page": 20,
    "search": "john@example.com"
}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "chat_id": 123456,
                "subscription_type": "Premium",
                "activation_in": "2025-01-01",
                "expired_in": "2026-01-01"
            }
        ],
        "total": 100,
        "per_page": 20
    }
}
```

---

### Support Bot Endpoints

#### 1. Get User Info
```http
POST /api/support-bot/user-info
```

**Request Body:**
```json
{
    "chat_id": 123456
}
```

---

#### 2. License Activation
```http
POST /api/support-bot/license-activation
```

**Request Body:**
```json
{
    "chat_id": 123456,
    "license": "ABC-123-XYZ"
}
```

---

### Error Responses

All endpoints return consistent error responses:

```json
{
    "status": false,
    "msg": "Error message description",
    "hint": "ERROR_CODE"
}
```

**Common Error Hints:**
- `LICENSE_EXPIRED`: User license has expired
- `API`: Exchange API error
- `API_CONNECTION`: Failed to connect to exchange
- `INVALID_INPUT`: Request validation failed
- `UNAUTHORIZED`: Authentication failed

---

## Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 5.7+ or MariaDB 10.3+
- Redis Server
- Node.js & NPM (for frontend assets)
- Nginx or Apache web server

### Step 1: Clone Repository
```bash
cd /home/signalvision-trader/htdocs/
git clone <repository-url> trader.signalvision.ai
cd trader.signalvision.ai
```

### Step 2: Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### Step 3: Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 4: Configure Environment Variables

Edit `.env` file:

```env
APP_NAME="Signal Trader"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://trader.signalvision.ai

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trader
DB_USERNAME=trader
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_telegram_bot_token

# API Configuration
SIGNAL_MANAGEMENT_END_POINT=https://manager.signalvision.ai
API_SECRET=your_api_secret

# Products
SIGNALSHOT_PRODUCT_ID=1021
SIGNALMANAGER_PRODUCT_ID=554

# Mail (Production SMTP)
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@signalvision.ai
MAIL_FROM_NAME="${APP_NAME}"
```

### Step 5: Database Setup
```bash
# Run migrations
php artisan migrate

# (Optional) Seed database with test data
php artisan db:seed
```

### Step 6: Build Frontend Assets
```bash
npm run build
```

### Step 7: Set Permissions
```bash
# Storage and cache directories
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Step 8: Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

### Step 9: Configure Web Server

#### Nginx Configuration Example:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name trader.signalvision.ai;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name trader.signalvision.ai;
    root /home/signalvision-trader/htdocs/trader.signalvision.ai/public;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Step 10: Configure Queue Workers

Create Supervisor configuration at `/etc/supervisor/conf.d/signal-trader-worker.conf`:

```ini
[program:signal-trader-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/signalvision-trader/htdocs/trader.signalvision.ai/artisan queue:work redis --queue=SignalTrader --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=signalvision-trader
numprocs=2
redirect_stderr=true
stdout_logfile=/home/signalvision-trader/htdocs/trader.signalvision.ai/storage/logs/worker.log
stopwaitsecs=3600
```

Start workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start signal-trader-worker:*
```

### Step 11: Set Up Telegram Webhook
```bash
# Set webhook URL for Telegram bot
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://trader.signalvision.ai/telegram-message-webhook"
```

### Step 12: Verify Installation
```bash
# Check application status
php artisan about

# Check queue workers
sudo supervisorctl status

# Test database connection
php artisan tinker
>>> \DB::connection()->getPdo();
```

---

## Configuration

### Application Configuration

#### config/app.php
Main application settings including timezone, locale, and service providers.

```php
'timezone' => 'UTC',
'locale' => 'en',
'fallback_locale' => 'en',
```

#### config/database.php
Database connection settings.

```php
'default' => env('DB_CONNECTION', 'mysql'),
```

#### config/queue.php
Queue configuration for background job processing.

```php
'default' => env('QUEUE_CONNECTION', 'redis'),
```

### Telegram Bot Configuration

Create Telegram bot via [@BotFather](https://t.me/botfather):

1. Send `/newbot` to BotFather
2. Follow prompts to create bot
3. Copy bot token to `TELEGRAM_BOT_TOKEN` in `.env`
4. Set webhook URL (see Installation Step 11)

### Exchange API Configuration

Users configure their exchange API keys via Telegram bot:
1. `/start` - Initialize bot
2. Select "API Keys" menu
3. Enter API Key
4. Enter API Secret
5. Keys are stored in `telegram_users` table

### Money Management Configuration

Default money management settings can be configured in database or via Telegram:
- Risk Percentage: 1-5%
- Max Exposure: 1-10 positions
- Daily Loss Limit: USD amount
- Leverage: 1-125x (exchange dependent)

---

## Deployment

### Production Deployment Checklist

#### Pre-Deployment:
- [ ] All tests passing
- [ ] Code reviewed and approved
- [ ] Database backup created
- [ ] Environment variables configured for production
- [ ] SSL certificate installed
- [ ] Queue workers configured with Supervisor

#### Deployment Steps:

1. **Enable Maintenance Mode**
```bash
php artisan down
```

2. **Pull Latest Code**
```bash
git pull origin main
```

3. **Update Dependencies**
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

4. **Run Migrations**
```bash
php artisan migrate --force
```

5. **Clear and Rebuild Caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. **Restart Services**
```bash
sudo supervisorctl restart signal-trader-worker:*
sudo systemctl restart php8.1-fpm
sudo systemctl reload nginx
```

7. **Disable Maintenance Mode**
```bash
php artisan up
```

#### Post-Deployment Verification:
```bash
# Check application
curl https://trader.signalvision.ai

# Check queue workers
sudo supervisorctl status

# Monitor logs
tail -f storage/logs/laravel.log
```

### Rollback Procedure

If deployment fails:

```bash
# 1. Enable maintenance mode
php artisan down

# 2. Revert code
git reset --hard <previous-commit-hash>

# 3. Restore database backup if needed
mysql -u trader -p trader < backup.sql

# 4. Clear caches
php artisan config:clear
php artisan cache:clear

# 5. Restart services
sudo supervisorctl restart signal-trader-worker:*

# 6. Disable maintenance mode
php artisan up
```

---

## Telegram Bot Usage

### User Commands & Workflow

#### 1. Getting Started

**Command:** `/start`

**Flow:**
1. User sends `/start` to bot
2. Bot checks license status
3. If no license, prompts for activation
4. If license valid, shows main menu

**Main Menu:**
```
┌───────────────────────┐
│  🔑 License           │
│  🛠️ API Keys          │
│  💰 Risk Management   │
│  🆘 Help              │
│  📞 Support           │
└───────────────────────┘
```

#### 2. License Activation

**Navigation:** Main Menu → 🔑 License

**Steps:**
1. Click "🔑 License"
2. Select "Activate License"
3. Enter license key (e.g., ABC-123-XYZ)
4. Bot validates with licensing server
5. If valid, activates account
6. Shows expiration date

**States:**
- Active: License valid and not expired
- Expired: License past expiration date
- Invalid: License not found in system

#### 3. API Key Configuration

**Navigation:** Main Menu → 🛠️ API Keys

**Steps:**
1. Click "🛠️ API Keys"
2. Select exchange (Bybit or Binance)
3. Enter API Key
4. Enter API Secret
5. Bot tests connection
6. Saves credentials if valid

**Security Notes:**
- API keys stored in database
- Should be encrypted (see Security Recommendations)
- Only permissions required: Read, Trade (NOT Withdraw)

#### 4. Risk Management Setup

**Navigation:** Main Menu → 💰 Risk Management

**Configurable Parameters:**

**Risk Percentage (%):**
- What it means: Percentage of balance to risk per trade
- Range: 0.5% - 5%
- Example: 2% risk on $1000 balance = $20 risk per trade

**Max Exposure:**
- What it means: Maximum number of open positions
- Range: 1-10 positions
- Example: Max 3 = Only 3 trades can be open simultaneously

**Daily Loss Limit ($):**
- What it means: Stop trading after losing this amount in a day
- Example: $100 daily loss limit
- Reset: Midnight UTC

**Leverage:**
- What it means: Multiplier for position size
- Range: 1x-125x (exchange dependent)
- Example: 10x leverage on $100 = $1000 position

**Trading Mode:**
- Demo: Practice trading with virtual balance
- Live: Real trading with exchange account

#### 5. Placing Trades

Trades are typically initiated by trading signals from external sources, but users can manually trigger trades via custom commands or API calls.

**Trade Confirmation Flow:**
1. Signal received (via webhook or bot command)
2. Bot validates:
   - License is active
   - API keys configured
   - Risk limits not exceeded
3. Calculates position size based on risk management
4. Places order on exchange
5. Sends confirmation to user

**Confirmation Message Example:**
```
✅ Trade Executed
Symbol: BTCUSDT
Side: BUY
Entry: $50,000
Quantity: 0.02 BTC
TP: $52,000
SL: $49,000
Risk: $20 (2%)
Leverage: 10x
```

#### 6. Monitoring Positions

**Command:** View Positions (from menu)

**Display:**
```
📊 Open Positions (2/3)

1️⃣ BTCUSDT
   Side: LONG
   Entry: $50,000
   Current: $50,500
   Size: 0.02 BTC
   PnL: +$10.00 (+1.0%)
   TP: $52,000
   SL: $49,000

2️⃣ ETHUSDT
   Side: SHORT
   Entry: $3,000
   Current: $2,980
   Size: 1.5 ETH
   PnL: +$30.00 (+1.0%)
   TP: $2,900
   SL: $3,100
```

#### 7. Closing Positions

**Methods:**
- Manual close via bot menu
- Automatic close when TP/SL hit
- Close all positions (emergency)

**Manual Close Steps:**
1. Select "Close Position" from menu
2. Choose position to close
3. Confirm action
4. Bot executes market close
5. Sends confirmation with final PnL

---

## Troubleshooting

### Common Issues

#### 1. Telegram Bot Not Responding

**Symptoms:**
- Messages sent to bot receive no response
- Bot shows offline

**Possible Causes:**
- Webhook not set correctly
- Application down or in maintenance mode
- Queue workers not running

**Solutions:**
```bash
# Check webhook status
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"

# Reset webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://trader.signalvision.ai/telegram-message-webhook"

# Check application is running
curl https://trader.signalvision.ai

# Check queue workers
sudo supervisorctl status signal-trader-worker:*

# Restart queue workers
sudo supervisorctl restart signal-trader-worker:*
```

#### 2. API Authentication Errors

**Symptoms:**
- "Unauthorized" errors when calling API
- 401 responses

**Possible Causes:**
- Missing `API-SECRET` header
- Incorrect API secret value
- Middleware not enabled

**Solutions:**
```bash
# Verify API secret in .env
cat .env | grep API_SECRET

# Test API with curl
curl -X POST https://trader.signalvision.ai/api/license/status \
  -H "API-SECRET: your-secret-here" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 12345}'

# Ensure middleware is uncommented in routes/api.php (lines 24, 124)
```

#### 3. Exchange API Errors

**Symptoms:**
- Trades fail to execute
- "API_CONNECTION" error hint
- "Invalid API key" messages

**Possible Causes:**
- Incorrect API credentials
- API key permissions insufficient
- Exchange API temporary downtime
- IP not whitelisted on exchange

**Solutions:**
1. Verify API key has correct permissions (Read + Trade, NO Withdraw)
2. Check API key is for correct exchange environment (mainnet vs testnet)
3. Whitelist server IP on exchange (if required)
4. Test API connection manually:
```bash
# For Binance
curl -X GET "https://fapi.binance.com/fapi/v1/time"

# For Bybit
curl -X GET "https://api.bybit.com/v5/market/time"
```

#### 4. License Validation Failures

**Symptoms:**
- "License expired" error when license should be valid
- Cannot activate license

**Possible Causes:**
- Licensing server unreachable
- Incorrect product ID
- License genuinely expired

**Solutions:**
```bash
# Check licensing server connectivity
curl https://manager.signalvision.ai/api/health

# Verify product ID in .env
cat .env | grep PRODUCT_ID

# Check license in database
php artisan tinker
>>> $sub = \App\Models\Subscription::where('license', 'ABC-123-XYZ')->first();
>>> $sub->expired_in;
```

#### 5. Queue Jobs Not Processing

**Symptoms:**
- Webhook messages not being processed
- Jobs stuck in queue
- Failed jobs accumulating

**Possible Causes:**
- Queue workers not running
- Redis connection issues
- Job failures not being caught

**Solutions:**
```bash
# Check queue workers
sudo supervisorctl status

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Restart queue workers
sudo supervisorctl restart signal-trader-worker:*

# Monitor queue in real-time (if Horizon installed)
# Visit: https://trader.signalvision.ai/horizon
```

#### 6. Database Connection Errors

**Symptoms:**
- "SQLSTATE" errors in logs
- Application crashes with database errors

**Possible Causes:**
- MySQL server down
- Incorrect database credentials
- Connection limit reached

**Solutions:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u trader -p -h 127.0.0.1

# Check connection limits
mysql -u root -p
> SHOW VARIABLES LIKE 'max_connections';
> SHOW STATUS LIKE 'Threads_connected';

# Verify credentials in .env match database
cat .env | grep DB_
```

### Debug Mode

To enable detailed error messages temporarily (NEVER in production with users):

```env
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

Then check logs:
```bash
tail -f storage/logs/laravel.log
```

Remember to disable debug mode after troubleshooting:
```env
APP_DEBUG=false
LOG_LEVEL=error
```

---

## Development Guide

### Local Development Setup

#### Using Laravel Sail (Docker):
```bash
# Install dependencies
composer install

# Start Docker containers
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Access application
# http://localhost
```

#### Traditional Setup:
```bash
# Copy environment file
cp .env.example .env

# Configure .env for local development
APP_ENV=local
APP_DEBUG=true
DB_DATABASE=trader_dev

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
# Access: http://127.0.0.1:8000
```

### Code Style

This project uses Laravel Pint for code formatting:

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style issues
./vendor/bin/pint
```

### Testing

#### Running Tests:
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

#### Writing Tests:

**Unit Test Example:**
```php
<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BinanceService;

class BinanceServiceTest extends TestCase
{
    public function test_calculates_position_size_correctly()
    {
        $service = new BinanceService();
        $size = $service->calculatePositionSize(
            balance: 1000,
            riskPercent: 2,
            entryPrice: 50000,
            stopLoss: 49000
        );

        $this->assertEquals(0.02, $size);
    }
}
```

**Feature Test Example:**
```php
<?php
namespace Tests\Feature;

use Tests\TestCase;

class LicenseApiTest extends TestCase
{
    public function test_license_validation_endpoint()
    {
        $response = $this->postJson('/api/license/validation', [
            'license' => 'TEST-123-ABC',
            'product_id' => '1021'
        ], [
            'API-SECRET' => env('API_SECRET')
        ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => true]);
    }
}
```

### Database Seeding

Create seeders for test data:

```bash
# Generate seeder
php artisan make:seeder TelegramUserSeeder

# Run seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=TelegramUserSeeder
```

### Creating New Features

#### 1. Create Migration
```bash
php artisan make:migration create_example_table
```

#### 2. Create Model
```bash
php artisan make:model Example -m
```

#### 3. Create Controller
```bash
php artisan make:controller ExampleController
```

#### 4. Add Routes
```php
// routes/api.php
Route::post('/example', [ExampleController::class, 'index']);
```

#### 5. Write Tests
```bash
php artisan make:test ExampleTest
```

### Debugging Tools

#### Laravel Tinker (REPL):
```bash
php artisan tinker
>>> $user = \App\Models\TelegramUser::find(1);
>>> $user->chat_id;
```

#### Query Logging:
```php
\DB::enableQueryLog();
// ... run queries
dd(\DB::getQueryLog());
```

#### Dump Helpers:
```php
dd($variable);        // Dump and die
dump($variable);      // Dump and continue
logger($variable);    // Log to file
```

### Git Workflow

```bash
# Create feature branch
git checkout -b feature/new-trading-feature

# Make changes and commit
git add .
git commit -m "Add new trading feature"

# Push to remote
git push origin feature/new-trading-feature

# Create pull request on GitHub/GitLab
# After review and approval, merge to main
```

### Environment Management

Development environments:
- **Local:** Developer machine
- **Staging:** Pre-production testing server
- **Production:** Live server

Use different `.env` files for each:
- `.env.local`
- `.env.staging`
- `.env.production`

---

## Additional Resources

### Official Documentation
- **Laravel:** https://laravel.com/docs/10.x
- **Telegram Bot API:** https://core.telegram.org/bots/api
- **Binance API:** https://binance-docs.github.io/apidocs/futures/en/
- **Bybit API:** https://bybit-exchange.github.io/docs/v5/intro

### Monitoring & Logging
- Laravel Horizon Dashboard: `/horizon`
- Log files: `storage/logs/laravel.log`

### Support & Contact
- Technical Issues: Create issue in repository
- License Questions: Contact SignalVision support
- Security Vulnerabilities: Report privately to security team

---

**Last Updated:** December 30, 2025
**Version:** 1.0.0
**Maintained By:** Development Team
