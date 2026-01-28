# SignalVision Admin - Laravel to Go Migration Documentation

> **Version:** 1.0
> **Date:** December 14, 2025
> **Purpose:** Comprehensive guide for migrating SignalVision Admin from Laravel (PHP) to Go

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Current Architecture](#2-current-architecture)
3. [Routes & Endpoints](#3-routes--endpoints)
4. [Controllers & Business Logic](#4-controllers--business-logic)
5. [Database Schema](#5-database-schema)
6. [Authentication & Middleware](#6-authentication--middleware)
7. [External API Integrations](#7-external-api-integrations)
8. [Frontend Structure](#8-frontend-structure)
9. [Configuration & Environment](#9-configuration--environment)
10. [Go Migration Strategy](#10-go-migration-strategy)
11. [Recommended Go Architecture](#11-recommended-go-architecture)
12. [Migration Checklist](#12-migration-checklist)
13. [Code Examples](#13-code-examples)

---

## 1. Project Overview

### Application Name
**SignalVision Admin Zone**

### Purpose
Centralized admin dashboard for managing a cryptocurrency trading signal platform with bot management, user monitoring, and Telegram integration.

### Current Technology Stack
- **Framework:** Laravel 10
- **Language:** PHP 8.1+
- **Database:** MySQL
- **Session:** File-based
- **Frontend:** Blade templates + Vanilla JS
- **Architecture:** MVC Pattern

### Core Functionality

The application serves as an admin panel that:

1. **User Management**
   - Manages users subscribed to cryptocurrency trading bots (Bot 1-8)
   - Tracks subscription status (paid/free) across two systems
   - Monitors user trading modes and performance metrics

2. **Dashboard Analytics**
   - Displays real-time trading statistics
   - Shows PnL (Profit and Loss) across demo and real accounts
   - Tracks active and waiting trades

3. **External Service Integration**
   - Integrates with **SignalManager API** for user subscriptions
   - Integrates with **SignalShot API** for trading execution data
   - Sends broadcast messages via **Telegram Bot API**

4. **Communication**
   - Individual user messaging via Telegram
   - Bulk message broadcasting to selected users
   - Real-time message delivery tracking

---

## 2. Current Architecture

### Directory Structure

```
admin.signalvision.ai/
├── app/
│   ├── Console/
│   │   └── Kernel.php
│   ├── Exceptions/
│   │   └── Handler.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php      # Admin authentication
│   │   │   ├── DashboardController.php # Dashboard stats
│   │   │   └── UserController.php      # User management
│   │   ├── Middleware/
│   │   │   ├── AdminAuth.php           # Custom auth middleware
│   │   │   └── [Laravel default middleware]
│   │   └── Kernel.php
│   ├── Models/
│   │   ├── AdminUser.php               # Admin authentication model
│   │   └── User.php                    # Unused Laravel default
│   └── Providers/
│       └── [Laravel service providers]
├── config/
│   ├── auth.php                        # Custom admin guard config
│   ├── database.php
│   ├── services.php                    # External API endpoints
│   └── [other Laravel configs]
├── database/
│   └── migrations/
│       ├── create_admin_users_table.php
│       └── [Laravel default migrations]
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin.css              # 2,591 lines custom styles
│   │   └── js/
│   │       └── admin.js               # 1,858 lines functionality
│   └── index.php
├── resources/
│   └── views/
│       ├── auth/
│       │   └── login.blade.php
│       ├── dashboard/
│       │   └── index.blade.php
│       ├── layouts/
│       │   ├── app.blade.php
│       │   └── dashboard.blade.php
│       └── users/
│           └── index.blade.php
├── routes/
│   ├── api.php                        # Minimal API routes
│   └── web.php                        # Main application routes
├── .env                               # Environment configuration
└── composer.json                      # PHP dependencies
```

### Design Patterns

1. **MVC (Model-View-Controller)**
   - Models: Data representation and database interaction
   - Views: Blade templates for UI rendering
   - Controllers: Business logic and request handling

2. **Repository Pattern**
   - Not explicitly implemented, but could benefit Go migration

3. **Service Layer**
   - External API calls encapsulated in controllers
   - Could be extracted to dedicated service classes in Go

---

## 3. Routes & Endpoints

### Web Routes (`routes/web.php`)

#### Public Routes (No Authentication)

| Method | URI | Controller@Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/` | Redirect | Redirects to `/login` |
| GET | `/login` | `AuthController@showLogin` | Display login form |
| POST | `/login` | `AuthController@login` | Process login credentials |
| POST | `/logout` | `AuthController@logout` | Logout and destroy session |
| GET | `/test` | Closure | Test endpoint for API debugging |

#### Protected Routes (Middleware: `admin`)

**Dashboard:**
| Method | URI | Controller@Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/dashboard` | `DashboardController@index` | Main dashboard with stats |

**User Management (Prefix: `/user`):**
| Method | URI | Controller@Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/user/all` | `UserController@all` | All users with filters |
| GET | `/user/paid` | `UserController@paid` | Paid users only |
| GET | `/user/free` | `UserController@free` | Free users only |
| POST | `/user/send-message` | `UserController@sendMessage` | Send Telegram message |

### API Routes (`routes/api.php`)

| Method | URI | Middleware | Description |
|--------|-----|------------|-------------|
| GET | `/api/user` | Sanctum | Get authenticated user (unused) |

---

## 4. Controllers & Business Logic

### AuthController (`app/Http/Controllers/AuthController.php`)

**Responsibility:** Admin authentication and session management

#### Methods

##### 1. `showLogin()`
```php
public function showLogin()
```
- Displays login page
- Redirects to dashboard if already authenticated
- Returns: `auth.login` view

##### 2. `login(Request $request)`
```php
public function login(Request $request)
```
- **Validation:**
  - `username`: required
  - `password`: required
- **Process:**
  1. Validates credentials against `admin_users` table
  2. Checks `status = 'active'`
  3. Uses `admin` guard for authentication
  4. Updates `last_login` timestamp
  5. Regenerates session for security
- **Returns:** Redirect to dashboard or back with errors

##### 3. `logout(Request $request)`
```php
public function logout(Request $request)
```
- Invalidates session
- Regenerates session token
- Redirects to login page

#### Security Features
- CSRF protection
- Session regeneration on login
- Status-based access control
- Password hashing (bcrypt)

---

### DashboardController (`app/Http/Controllers/DashboardController.php`)

**Responsibility:** Aggregate and display dashboard statistics

#### Method

##### `index()`
```php
public function index()
```

**External API Calls:**
1. Fetch trade statistics from Manager API:
   - Endpoint: `POST /api/admin/trades`
   - Returns: waiting trades, running trades, demo PnL, real PnL

2. Fetch recent 5 users from both APIs:
   - Manager API: `POST /api/admin/users` (page=1, per_page=5)
   - Shot API: `POST /api/admin/users` (page=1, per_page=5)

3. Fetch all users for count calculations:
   - Manager API: `POST /api/admin/users` (page=1, per_page=1000)
   - Shot API: `POST /api/admin/users` (page=1, per_page=1000)

**Data Processing:**
- Merges user data by `user_id`
- Calculates:
  - Total users (unique user_ids)
  - Paid users (where `manager_is_paid == true`)
  - Free users (total - paid)

**Response Data:**
```php
[
    'users' => Collection,      // Recent 5 users
    'total_users' => int,
    'paid_users' => int,
    'free_users' => int,
    'trades' => [
        'waiting' => int,
        'running' => int,
        'demo_pnl' => float,
        'real_pnl' => float
    ]
]
```

---

### UserController (`app/Http/Controllers/UserController.php`)

**Responsibility:** User listing, filtering, and messaging

#### Private Methods

##### `fetchUsers($page, $perPage, $filter, $search, $filters)`
```php
private function fetchUsers($page, $perPage, $filter = 'all', $search = '', $filters = [])
```

**Process:**
1. Fetches ALL users (1000 max) from both Manager and Shot APIs
2. Merges data by `user_id`
3. Applies client-side filters:
   - **Legacy filter:** `paid`/`free` (based on `manager_is_paid`)
   - **Manager status:** `paid`/`trial`/`all`
   - **Shot status:** `paid`/`trial`/`all`
   - **Mode:** `active`/`passive`/`all` (money_management_status)
   - **Bot:** `bot1` through `bot8`/`all`
   - **Search:** handled on backend
4. Implements client-side pagination
5. Returns paginated collection

**Filter Parameters:**
- `page` (default: 1)
- `per_page` (default: 20, options: 20/50/100)
- `search` (text search)
- `manager` (all/paid/trial)
- `shot` (all/paid/trial)
- `mod` (all/active/passive)
- `bot` (all/bot1-bot8)

#### Public Methods

##### 1. `all(Request $request)`
Shows all users with applied filters

##### 2. `paid(Request $request)`
Shows paid users (legacy filter + dynamic filters)

##### 3. `free(Request $request)`
Shows free users (legacy filter + dynamic filters)

##### 4. `sendMessage(Request $request)`
```php
public function sendMessage(Request $request)
```

**Validation:**
- `user_id`: required
- `message`: required

**Process:**
1. Sends message via Telegram Bot API
   - Endpoint: `https://api.telegram.org/bot{TOKEN}/sendMessage`
   - Method: POST
   - Body:
     ```json
     {
       "chat_id": "user_id",
       "text": "message",
       "parse_mode": "HTML"
     }
     ```
2. Returns JSON response with success/failure status

---

## 5. Database Schema

### AdminUser Table (`admin_users`)

```sql
CREATE TABLE admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Model Properties:**
- **Fillable:** username, email, password, full_name, role, status, last_login
- **Hidden:** password
- **Casts:** last_login => datetime
- **Extends:** Illuminate\Foundation\Auth\User

### Users Table (`users`)

Standard Laravel users table - **NOT ACTIVELY USED** in the application.

All user data is fetched from external APIs, not stored locally.

### Additional Tables

- `failed_jobs` - Laravel failed queue jobs
- `password_reset_tokens` - Password reset functionality
- `personal_access_tokens` - Laravel Sanctum tokens (unused)

---

## 6. Authentication & Middleware

### Custom Authentication Guard

**Configuration (`config/auth.php`):**

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',  // Default, not used
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admin_users',  // ACTIVE GUARD
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'admin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\AdminUser::class,
    ],
],
```

### AdminAuth Middleware (`app/Http/Middleware/AdminAuth.php`)

**Applied to:** All protected routes via `admin` alias

**Logic:**
```php
public function handle($request, Closure $next)
{
    if (!Auth::guard('admin')->check()) {
        return redirect()->route('login');
    }

    $user = Auth::guard('admin')->user();
    if ($user->status !== 'active') {
        Auth::guard('admin')->logout();
        return redirect()->route('login')
            ->with('error', 'Your account is inactive');
    }

    return $next($request);
}
```

**Features:**
- Checks authentication via `admin` guard
- Verifies user status is `active`
- Auto-logout for inactive users
- Redirects unauthenticated users to login

### HTTP Kernel Middleware Stack

**Global Middleware:**
- TrustProxies
- HandleCors
- PreventRequestsDuringMaintenance
- ValidatePostSize
- TrimStrings
- ConvertEmptyStringsToNull

**Web Middleware Group:**
- EncryptCookies
- AddQueuedCookiesToResponse
- StartSession
- ShareErrorsFromSession
- VerifyCsrfToken
- SubstituteBindings

**Middleware Aliases:**
- `admin` => AdminAuth (custom)
- Standard Laravel aliases (auth, guest, throttle, etc.)

---

## 7. External API Integrations

### Configuration (`config/services.php`)

```php
'api' => [
    'secret' => env('API_SECRET'), // 'TRT56WTWRT'
    'manager_end_point' => env('MANAGER_API_END_POINT'),
    // https://manager.signalvision.ai
    'shot_end_point' => env('SHOT_API_END_POINT'),
    // https://trader.signalvision.ai
    'support_bot_token' => env('SUPPORT_BOT_TOKEN'),
    // Telegram bot token
]
```

### API Endpoints

#### 1. Manager API (`https://manager.signalvision.ai`)

**Headers:**
```
API-SECRET: TRT56WTWRT
Content-Type: application/json
```

**Endpoints:**

##### `POST /api/admin/trades`
Get trade statistics

**Request:**
```json
{}
```

**Response:**
```json
{
    "waiting": 12,
    "running": 5,
    "demo_pnl": 1250.50,
    "real_pnl": 3420.75
}
```

##### `POST /api/admin/users`
Get user data with Manager subscription info

**Request:**
```json
{
    "page": 1,
    "per_page": 1000,
    "search": "optional_search_term"
}
```

**Response:**
```json
{
    "users": [
        {
            "user_id": "123456789",
            "username": "johndoe",
            "bot": 1,
            "manager_is_paid": true,
            ...
        }
    ],
    "total": 150,
    "current_page": 1,
    "last_page": 1
}
```

---

#### 2. Shot API (`https://trader.signalvision.ai`)

**Headers:**
```
API-SECRET: TRT56WTWRT
Content-Type: application/json
```

**Endpoints:**

##### `POST /api/admin/users`
Get user data with Shot subscription info

**Request:**
```json
{
    "page": 1,
    "per_page": 1000
}
```

**Response:**
```json
{
    "users": [
        {
            "user_id": "123456789",
            "shot_is_paid": true,
            "money_management_status": "active",
            "trade_stats": {
                "total": 45,
                "active": 3,
                "win_rate": 68.5,
                "total_demo_pnl": 500.25,
                "total_real_pnl": 1200.50,
                "total_pnl": 1700.75
            },
            ...
        }
    ]
}
```

---

#### 3. Telegram Bot API

**Endpoint:**
```
POST https://api.telegram.org/bot{BOT_TOKEN}/sendMessage
```

**Request:**
```json
{
    "chat_id": "123456789",
    "text": "Hello from SignalVision Admin!",
    "parse_mode": "HTML"
}
```

**Response:**
```json
{
    "ok": true,
    "result": {
        "message_id": 456,
        "date": 1234567890,
        ...
    }
}
```

---

### Merged User Data Structure

When data from Manager and Shot APIs is merged by `user_id`:

```php
[
    'user_id' => '123456789',          // Telegram user ID
    'username' => 'johndoe',           // Telegram username
    'bot' => 1,                        // Bot number (1-8)

    // Manager data
    'manager_is_paid' => true,         // Subscription status

    // Shot data
    'shot_is_paid' => true,            // Subscription status

    // Trading settings
    'money_management_status' => 'active',  // 'active' or 'passive'
    'money_management_uni_strategy_status' => 'enabled',
    'money_management_profit_strategy' => 'fixed',
    'money_management_profit_strategy_tp' => 50,
    'money_management_uni_leverage' => 10,
    'money_management_risk' => 2.5,
    'money_management_daily_loss' => 5.0,
    'money_management_max_exposure' => 10.0,
    'money_management_trade_limit' => 5,
    'money_management_stop_trades' => 3.0,
    'money_management_exchange' => 'bybit',

    // Exchange connections
    'bybit_is_connected' => true,
    'bybit_balance' => 5000.00,
    'binance_is_connected' => false,
    'binance_balance' => 0.00,

    // Trade statistics
    'trade_stats' => [
        'total' => 45,                 // Total signals
        'active' => 3,                 // Active trades
        'win_rate' => 68.5,            // Win percentage
        'total_demo_pnl' => 500.25,    // Demo PnL
        'total_real_pnl' => 1200.50,   // Real PnL
        'total_pnl' => 1700.75         // Combined PnL
    ],

    // Activity
    'recent_activity' => [],
    'created_at' => '2024-01-15T10:30:00Z',
    'updated_at' => '2024-12-14T14:20:00Z'
]
```

---

## 8. Frontend Structure

### Views (Blade Templates)

**Layouts:**
- `resources/views/layouts/app.blade.php` - Base HTML structure
- `resources/views/layouts/dashboard.blade.php` - Dashboard layout (extends app)

**Pages:**
- `resources/views/auth/login.blade.php` - Login page
- `resources/views/dashboard/index.blade.php` - Main dashboard
- `resources/views/users/index.blade.php` - User management table

### Assets

#### CSS (`public/assets/css/admin.css` - 2,591 lines)

**Features:**
- Custom dark theme design
- Responsive grid system
- Component styles: cards, tables, modals, badges, buttons
- Gradient color scheme (cyan, blue, green, orange, red)
- Modern glassmorphism effects

**Color Palette:**
```css
--bg-primary: #0a0e1a
--bg-secondary: #1a1f35
--text-primary: #e2e8f0
--accent-cyan: #06b6d4
--accent-blue: #3b82f6
--accent-green: #10b981
--accent-orange: #f59e0b
--accent-red: #ef4444
```

#### JavaScript (`public/assets/js/admin.js` - 1,858 lines)

**Key Functionality:**

1. **Icon Management**
   - Lucide icons integration
   - Dynamic icon loading

2. **Data Visualization**
   - Chart.js for graphs
   - Real-time stat updates

3. **User Modal**
   - User detail view
   - Profile information display
   - Trading statistics

4. **Messaging System**
   - Individual message sending
   - Bulk broadcast functionality
   - Progress tracking for batch operations

5. **Filtering & Pagination**
   - Dynamic filter handlers
   - Pagination controls
   - Search functionality

6. **Data Export**
   - CSV export capability
   - Selected users export

**External Libraries (CDN):**
```html
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

### UI Components

#### Dashboard Page

**Statistics Cards:**
- Total Users
- Waiting Trades
- Running Trades
- Demo PnL (color-coded: green/red)
- Real PnL (color-coded: green/red)

**Recent Users Table:**
- Shows last 5 users
- Columns: Username, Bot, Manager Status, Shot Status, Mode, Win Rate, PnL

**Activity Feed:**
- Placeholder for future implementation

#### Users Page

**Filter Section:**
- Manager Status dropdown (All/Paid/Trial)
- Shot Status dropdown (All/Paid/Trial)
- Mode dropdown (All/Active/Passive)
- Bot selection (All/Bot 1-8)
- Search input (username/user_id)

**Action Bar:**
- Bulk actions: Message Selected, Export, Ban
- Per-page selector (20/50/100)

**User Table Columns:**
1. Checkbox (for bulk selection)
2. User info (username, chat ID)
3. Bot number
4. Manager status (Paid/Free badge)
5. Shot status (Paid/Free badge)
6. Trading mode (Active/Passive badge)
7. Strategy details
8. Leverage
9. Risk percentage
10. API connections (Bybit/Binance icons)
11. Signal count
12. Trade count
13. Win rate (color-coded percentage)
14. Demo PnL
15. Real PnL
16. Last active (relative time)
17. Registration date
18. Actions dropdown (View, Message, Edit, More)

**Pagination:**
- Previous/Next buttons
- Page number display
- Per-page options

#### Modals

**User Detail Modal:**
- Full user profile
- Complete trading settings
- Exchange balances
- Trade history
- Activity log

**Message Modal:**
- Message textarea
- Character counter
- Send/Cancel buttons

**Broadcast Modal:**
- Recipient count
- Message input
- Progress bar
- Success/failure tracking

---

## 9. Configuration & Environment

### Environment Variables (`.env`)

```env
# Application
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=admin
DB_USERNAME=admin
DB_PASSWORD='Q1m0DeTvFbmpqKgy2gyE'

# Session & Cache
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# External APIs
MANAGER_API_END_POINT="https://manager.signalvision.ai"
SHOT_API_END_POINT="https://trader.signalvision.ai"
API_SECRET="TRT56WTWRT"
SUPPORT_BOT_TOKEN="7898051144:AAEQPhfIGOJ7LnDOqMTJxf6MW-AEVmyKzCM"

# Mail (not actively used)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Database Configuration

**Connection:** MySQL
**Charset:** utf8mb4
**Collation:** utf8mb4_unicode_ci
**Engine:** InnoDB
**Strict Mode:** Enabled
**Timezone:** UTC

### Dependencies

**Composer (PHP):**
```json
{
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.10",
        "guzzlehttp/guzzle": "^7.2",
        "laravel/sanctum": "^3.3",
        "laravel/tinker": "^2.8"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.1",
        "laravel/sail": "^1.18",
        "spatie/laravel-ignition": "^2.0"
    }
}
```

**NPM (Node.js):**
```json
{
    "devDependencies": {
        "vite": "^5.0.0",
        "laravel-vite-plugin": "^1.0.0",
        "axios": "^1.6.4"
    }
}
```

---

## 10. Go Migration Strategy

### Why Migrate to Go?

**Performance Benefits:**
- 10-100x faster than PHP
- Compiled binary (no runtime overhead)
- Superior concurrency with goroutines
- Lower memory footprint

**Operational Benefits:**
- Single binary deployment (no dependencies)
- Smaller Docker images (10-20MB vs 500MB+)
- Better resource utilization
- Built-in HTTP server (no Apache/Nginx required)

**Development Benefits:**
- Strong static typing
- Better error handling
- Native concurrency primitives
- Excellent standard library
- Fast compilation

### Migration Approach

**Recommended Strategy:** Phased migration

**Phase 1: Backend API (Weeks 1-2)**
- Implement authentication
- Port controllers to handlers
- Set up database layer
- Create API clients for external services
- Implement business logic

**Phase 2: Frontend Integration (Weeks 3-4)**
- Port Blade templates to Go templates
- Integrate existing CSS/JS
- Implement server-side rendering
- Test all user flows

**Phase 3: Optimization (Week 5)**
- Add caching layer (Redis)
- Implement concurrent API calls
- Load testing and profiling
- Security hardening

**Phase 4: Deployment (Week 6)**
- Create Docker image
- Set up CI/CD pipeline
- Production deployment
- Monitoring setup














---

## 11. Recommended Go Architecture

### Project Structure

```
signalvision-admin/
├── cmd/
│   └── server/
│       └── main.go                    # Application entry point
├── internal/
│   ├── api/                           # External API clients
│   │   ├── manager/
│   │   │   └── client.go              # Manager API client
│   │   ├── shot/
│   │   │   └── client.go              # Shot API client
│   │   └── telegram/
│   │       └── client.go              # Telegram Bot API
│   ├── auth/                          # Authentication logic
│   │   ├── session.go                 # Session management
│   │   └── middleware.go              # Auth middleware
│   ├── config/                        # Configuration
│   │   └── config.go                  # App configuration
│   ├── handlers/                      # HTTP handlers (controllers)
│   │   ├── auth.go                    # Login/logout
│   │   ├── dashboard.go               # Dashboard
│   │   └── users.go                   # User management
│   ├── models/                        # Data models
│   │   ├── admin_user.go              # Admin user model
│   │   └── user.go                    # Trading user model
│   ├── repository/                    # Database layer
│   │   └── admin_user.go              # Admin CRUD operations
│   ├── services/                      # Business logic
│   │   ├── user_service.go            # User data aggregation
│   │   └── stats_service.go           # Statistics calculation
│   └── middleware/                    # HTTP middleware
│       ├── auth.go                    # Authentication
│       ├── logging.go                 # Request logging
│       └── recovery.go                # Panic recovery
├── pkg/                               # Reusable packages
│   ├── httputil/                      # HTTP utilities
│   ├── timeutil/                      # Time utilities
│   └── validator/                     # Input validation
├── web/
│   ├── static/                        # Static assets
│   │   ├── css/
│   │   │   └── admin.css
│   │   └── js/
│   │       └── admin.js
│   └── templates/                     # HTML templates
│       ├── auth/
│       │   └── login.html
│       ├── dashboard/
│       │   └── index.html
│       ├── layouts/
│       │   └── base.html
│       └── users/
│           └── index.html
├── migrations/                        # Database migrations
│   └── 001_create_admin_users.sql
├── Dockerfile                         # Docker configuration
├── docker-compose.yml                 # Local development
├── Makefile                           # Build automation
├── .env.example                       # Environment template
├── go.mod                             # Go dependencies
└── README.md                          # Project documentation
```

### Recommended Go Libraries

**HTTP Framework:**
```bash
go get github.com/gin-gonic/gin
# OR
go get github.com/labstack/echo/v4
```

**Database:**
```bash
go get gorm.io/gorm
go get gorm.io/driver/mysql
```

**Session Management:**
```bash
go get github.com/gorilla/sessions
```

**HTTP Client:**
```bash
go get github.com/go-resty/resty/v2
```

**Configuration:**
```bash
go get github.com/spf13/viper
go get github.com/joho/godotenv
```

**Validation:**
```bash
go get github.com/go-playground/validator/v10
```

**Password Hashing:**
```bash
go get golang.org/x/crypto/bcrypt
```

**Logging:**
```bash
go get github.com/sirupsen/logrus
# OR
go get go.uber.org/zap
```

**Caching (Optional but recommended):**
```bash
go get github.com/go-redis/redis/v8
```

---

## 12. Migration Checklist

### Phase 1: Project Setup

- [ ] Initialize Go module (`go mod init`)
- [ ] Set up project directory structure
- [ ] Install core dependencies (Gin/Echo, GORM, etc.)
- [ ] Create `.env.example` and configuration loader
- [ ] Set up database connection
- [ ] Create Makefile for common tasks

### Phase 2: Database Layer

- [ ] Create `admin_users` table migration
- [ ] Implement `AdminUser` model
- [ ] Create `AdminUserRepository` interface
- [ ] Implement repository with GORM
- [ ] Write unit tests for repository

### Phase 3: Authentication

- [ ] Implement session management
- [ ] Create auth middleware
- [ ] Implement login handler
- [ ] Implement logout handler
- [ ] Create login template
- [ ] Test authentication flow

### Phase 4: External API Clients

- [ ] Create Manager API client
  - [ ] `FetchUsers()` method
  - [ ] `FetchTrades()` method
  - [ ] Error handling
- [ ] Create Shot API client
  - [ ] `FetchUsers()` method
  - [ ] Error handling
- [ ] Create Telegram Bot API client
  - [ ] `SendMessage()` method
  - [ ] Error handling
- [ ] Write integration tests

### Phase 5: Business Logic

- [ ] Create user service
  - [ ] Data merging logic
  - [ ] Filtering logic
  - [ ] Pagination logic
- [ ] Create stats service
  - [ ] User count calculations
  - [ ] Trade statistics
- [ ] Write unit tests

### Phase 6: HTTP Handlers

- [ ] Dashboard handler
  - [ ] Fetch and aggregate data
  - [ ] Render template
- [ ] User handlers
  - [ ] All users endpoint
  - [ ] Paid users endpoint
  - [ ] Free users endpoint
  - [ ] Send message endpoint
- [ ] Create route definitions

### Phase 7: Frontend Templates

- [ ] Create base layout template
- [ ] Port login page
- [ ] Port dashboard page
- [ ] Port users page
- [ ] Copy CSS and JavaScript assets
- [ ] Test all pages

### Phase 8: Optimization

- [ ] Implement Redis caching
  - [ ] Cache API responses
  - [ ] Cache session data
- [ ] Implement concurrent API calls with goroutines
- [ ] Add request logging
- [ ] Add error tracking
- [ ] Optimize database queries

### Phase 9: Testing

- [ ] Write unit tests (80%+ coverage)
- [ ] Write integration tests
- [ ] E2E testing
- [ ] Load testing with `k6` or `wrk`
- [ ] Security audit

### Phase 10: Deployment

- [ ] Create optimized Dockerfile
- [ ] Create docker-compose.yml
- [ ] Set up CI/CD pipeline (GitHub Actions)
- [ ] Configure production environment
- [ ] Deploy to staging
- [ ] Deploy to production
- [ ] Set up monitoring (Prometheus/Grafana)

---

## 13. Code Examples

### Example 1: Main Application Entry Point

**File:** `cmd/server/main.go`

```go
package main

import (
    "log"
    "signalvision-admin/internal/config"
    "signalvision-admin/internal/handlers"
    "signalvision-admin/internal/middleware"
    "signalvision-admin/internal/repository"
    "signalvision-admin/internal/api/manager"
    "signalvision-admin/internal/api/shot"
    "signalvision-admin/internal/api/telegram"
    "signalvision-admin/internal/services"

    "github.com/gin-gonic/gin"
    "gorm.io/driver/mysql"
    "gorm.io/gorm"
)

func main() {
    // Load configuration
    cfg, err := config.Load()
    if err != nil {
        log.Fatalf("Failed to load config: %v", err)
    }

    // Database connection
    db, err := gorm.Open(mysql.Open(cfg.Database.DSN()), &gorm.Config{})
    if err != nil {
        log.Fatalf("Failed to connect to database: %v", err)
    }

    // Repositories
    adminRepo := repository.NewAdminUserRepository(db)

    // External API clients
    managerClient := manager.NewClient(cfg.API.ManagerEndpoint, cfg.API.Secret)
    shotClient := shot.NewClient(cfg.API.ShotEndpoint, cfg.API.Secret)
    telegramClient := telegram.NewClient(cfg.API.TelegramToken)

    // Services
    userService := services.NewUserService(managerClient, shotClient)
    statsService := services.NewStatsService(managerClient, shotClient)

    // HTTP router
    router := gin.Default()

    // Middleware
    router.Use(middleware.Recovery())
    router.Use(middleware.Logger())

    // Static files
    router.Static("/assets", "./web/static")

    // Templates
    router.LoadHTMLGlob("web/templates/**/*")

    // Handlers
    authHandler := handlers.NewAuthHandler(adminRepo, cfg.Session)
    dashboardHandler := handlers.NewDashboardHandler(userService, statsService)
    userHandler := handlers.NewUserHandler(userService, telegramClient)

    // Public routes
    router.GET("/", func(c *gin.Context) {
        c.Redirect(302, "/login")
    })
    router.GET("/login", authHandler.ShowLogin)
    router.POST("/login", authHandler.Login)
    router.POST("/logout", authHandler.Logout)

    // Protected routes
    authorized := router.Group("/")
    authorized.Use(middleware.AuthMiddleware(cfg.Session))
    {
        authorized.GET("/dashboard", dashboardHandler.Index)
        authorized.GET("/user/all", userHandler.All)
        authorized.GET("/user/paid", userHandler.Paid)
        authorized.GET("/user/free", userHandler.Free)
        authorized.POST("/user/send-message", userHandler.SendMessage)
    }

    // Start server
    log.Printf("Starting server on %s", cfg.Server.Address)
    if err := router.Run(cfg.Server.Address); err != nil {
        log.Fatalf("Server failed: %v", err)
    }
}
```

---

### Example 2: Configuration Management

**File:** `internal/config/config.go`

```go
package config

import (
    "fmt"
    "github.com/joho/godotenv"
    "github.com/spf13/viper"
)

type Config struct {
    Server   ServerConfig
    Database DatabaseConfig
    API      APIConfig
    Session  SessionConfig
}

type ServerConfig struct {
    Address string
    Port    int
}

type DatabaseConfig struct {
    Host     string
    Port     int
    Database string
    Username string
    Password string
}

type APIConfig struct {
    ManagerEndpoint string
    ShotEndpoint    string
    Secret          string
    TelegramToken   string
}

type SessionConfig struct {
    Secret   string
    MaxAge   int
    Secure   bool
    HttpOnly bool
}

func Load() (*Config, error) {
    // Load .env file
    godotenv.Load()

    // Set defaults
    viper.SetDefault("server.port", 8080)
    viper.SetDefault("session.max_age", 7200)
    viper.SetDefault("session.secure", false)
    viper.SetDefault("session.http_only", true)

    // Bind environment variables
    viper.AutomaticEnv()

    cfg := &Config{
        Server: ServerConfig{
            Port:    viper.GetInt("PORT"),
            Address: fmt.Sprintf(":%d", viper.GetInt("PORT")),
        },
        Database: DatabaseConfig{
            Host:     viper.GetString("DB_HOST"),
            Port:     viper.GetInt("DB_PORT"),
            Database: viper.GetString("DB_DATABASE"),
            Username: viper.GetString("DB_USERNAME"),
            Password: viper.GetString("DB_PASSWORD"),
        },
        API: APIConfig{
            ManagerEndpoint: viper.GetString("MANAGER_API_END_POINT"),
            ShotEndpoint:    viper.GetString("SHOT_API_END_POINT"),
            Secret:          viper.GetString("API_SECRET"),
            TelegramToken:   viper.GetString("SUPPORT_BOT_TOKEN"),
        },
        Session: SessionConfig{
            Secret:   viper.GetString("SESSION_SECRET"),
            MaxAge:   viper.GetInt("session.max_age"),
            Secure:   viper.GetBool("session.secure"),
            HttpOnly: viper.GetBool("session.http_only"),
        },
    }

    return cfg, nil
}

func (d *DatabaseConfig) DSN() string {
    return fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True&loc=Local",
        d.Username,
        d.Password,
        d.Host,
        d.Port,
        d.Database,
    )
}
```

---

### Example 3: AdminUser Model

**File:** `internal/models/admin_user.go`

```go
package models

import (
    "time"
    "golang.org/x/crypto/bcrypt"
)

type AdminUser struct {
    ID        uint       `gorm:"primaryKey"`
    Username  string     `gorm:"uniqueIndex;size:50;not null"`
    Email     string     `gorm:"uniqueIndex;size:100;not null"`
    Password  string     `gorm:"size:255;not null"`
    FullName  string     `gorm:"size:100"`
    Role      string     `gorm:"type:enum('admin','super_admin');default:'admin'"`
    Status    string     `gorm:"type:enum('active','inactive');default:'active';index"`
    LastLogin *time.Time `gorm:"type:timestamp"`
    CreatedAt time.Time
    UpdatedAt time.Time
}

func (AdminUser) TableName() string {
    return "admin_users"
}

func (u *AdminUser) SetPassword(password string) error {
    hashedPassword, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
    if err != nil {
        return err
    }
    u.Password = string(hashedPassword)
    return nil
}

func (u *AdminUser) CheckPassword(password string) bool {
    err := bcrypt.CompareHashAndPassword([]byte(u.Password), []byte(password))
    return err == nil
}

func (u *AdminUser) IsActive() bool {
    return u.Status == "active"
}
```

---

### Example 4: AdminUser Repository

**File:** `internal/repository/admin_user.go`

```go
package repository

import (
    "signalvision-admin/internal/models"
    "gorm.io/gorm"
)

type AdminUserRepository interface {
    FindByUsername(username string) (*models.AdminUser, error)
    FindByID(id uint) (*models.AdminUser, error)
    UpdateLastLogin(id uint) error
}

type adminUserRepository struct {
    db *gorm.DB
}

func NewAdminUserRepository(db *gorm.DB) AdminUserRepository {
    return &adminUserRepository{db: db}
}

func (r *adminUserRepository) FindByUsername(username string) (*models.AdminUser, error) {
    var user models.AdminUser
    err := r.db.Where("username = ?", username).First(&user).Error
    if err != nil {
        return nil, err
    }
    return &user, nil
}

func (r *adminUserRepository) FindByID(id uint) (*models.AdminUser, error) {
    var user models.AdminUser
    err := r.db.First(&user, id).Error
    if err != nil {
        return nil, err
    }
    return &user, nil
}

func (r *adminUserRepository) UpdateLastLogin(id uint) error {
    return r.db.Model(&models.AdminUser{}).
        Where("id = ?", id).
        Update("last_login", gorm.Expr("NOW()")).Error
}
```

---

### Example 5: Auth Middleware

**File:** `internal/middleware/auth.go`

```go
package middleware

import (
    "net/http"
    "signalvision-admin/internal/config"
    "signalvision-admin/internal/repository"

    "github.com/gin-gonic/gin"
    "github.com/gorilla/sessions"
)

var store *sessions.CookieStore

func InitSessionStore(cfg config.SessionConfig) {
    store = sessions.NewCookieStore([]byte(cfg.Secret))
    store.Options = &sessions.Options{
        MaxAge:   cfg.MaxAge,
        HttpOnly: cfg.HttpOnly,
        Secure:   cfg.Secure,
        Path:     "/",
    }
}

func AuthMiddleware(cfg config.SessionConfig) gin.HandlerFunc {
    if store == nil {
        InitSessionStore(cfg)
    }

    return func(c *gin.Context) {
        session, err := store.Get(c.Request, "admin_session")
        if err != nil {
            c.Redirect(http.StatusFound, "/login")
            c.Abort()
            return
        }

        userID, ok := session.Values["user_id"].(uint)
        if !ok {
            c.Redirect(http.StatusFound, "/login")
            c.Abort()
            return
        }

        // Check user status
        adminRepo := c.MustGet("adminRepo").(repository.AdminUserRepository)
        user, err := adminRepo.FindByID(userID)
        if err != nil || !user.IsActive() {
            // Clear session
            session.Values["user_id"] = nil
            session.Save(c.Request, c.Writer)

            c.Redirect(http.StatusFound, "/login")
            c.Abort()
            return
        }

        // Store user in context
        c.Set("user", user)
        c.Next()
    }
}
```

---

### Example 6: Manager API Client

**File:** `internal/api/manager/client.go`

```go
package manager

import (
    "encoding/json"
    "fmt"
    "github.com/go-resty/resty/v2"
)

type Client struct {
    baseURL string
    secret  string
    http    *resty.Client
}

type UserResponse struct {
    Users       []User `json:"users"`
    Total       int    `json:"total"`
    CurrentPage int    `json:"current_page"`
    LastPage    int    `json:"last_page"`
}

type User struct {
    UserID        string `json:"user_id"`
    Username      string `json:"username"`
    Bot           int    `json:"bot"`
    ManagerIsPaid bool   `json:"manager_is_paid"`
    // ... other fields
}

type TradeStats struct {
    Waiting int     `json:"waiting"`
    Running int     `json:"running"`
    DemoPnl float64 `json:"demo_pnl"`
    RealPnl float64 `json:"real_pnl"`
}

func NewClient(baseURL, secret string) *Client {
    return &Client{
        baseURL: baseURL,
        secret:  secret,
        http:    resty.New(),
    }
}

func (c *Client) FetchUsers(page, perPage int, search string) (*UserResponse, error) {
    var result UserResponse

    resp, err := c.http.R().
        SetHeader("API-SECRET", c.secret).
        SetHeader("Content-Type", "application/json").
        SetBody(map[string]interface{}{
            "page":     page,
            "per_page": perPage,
            "search":   search,
        }).
        Post(fmt.Sprintf("%s/api/admin/users", c.baseURL))

    if err != nil {
        return nil, fmt.Errorf("request failed: %w", err)
    }

    if resp.StatusCode() != 200 {
        return nil, fmt.Errorf("API returned status %d", resp.StatusCode())
    }

    if err := json.Unmarshal(resp.Body(), &result); err != nil {
        return nil, fmt.Errorf("failed to parse response: %w", err)
    }

    return &result, nil
}

func (c *Client) FetchTrades() (*TradeStats, error) {
    var result TradeStats

    resp, err := c.http.R().
        SetHeader("API-SECRET", c.secret).
        SetHeader("Content-Type", "application/json").
        Post(fmt.Sprintf("%s/api/admin/trades", c.baseURL))

    if err != nil {
        return nil, fmt.Errorf("request failed: %w", err)
    }

    if resp.StatusCode() != 200 {
        return nil, fmt.Errorf("API returned status %d", resp.StatusCode())
    }

    if err := json.Unmarshal(resp.Body(), &result); err != nil {
        return nil, fmt.Errorf("failed to parse response: %w", err)
    }

    return &result, nil
}
```

---

### Example 7: User Service (Data Merging)

**File:** `internal/services/user_service.go`

```go
package services

import (
    "signalvision-admin/internal/api/manager"
    "signalvision-admin/internal/api/shot"
    "signalvision-admin/internal/models"
    "sync"
)

type UserService struct {
    managerClient *manager.Client
    shotClient    *shot.Client
}

func NewUserService(managerClient *manager.Client, shotClient *shot.Client) *UserService {
    return &UserService{
        managerClient: managerClient,
        shotClient:    shotClient,
    }
}

func (s *UserService) FetchAllUsers(search string) ([]models.User, error) {
    var wg sync.WaitGroup
    var managerUsers []manager.User
    var shotUsers []shot.User
    var managerErr, shotErr error

    // Fetch data concurrently
    wg.Add(2)

    go func() {
        defer wg.Done()
        resp, err := s.managerClient.FetchUsers(1, 1000, search)
        if err != nil {
            managerErr = err
            return
        }
        managerUsers = resp.Users
    }()

    go func() {
        defer wg.Done()
        resp, err := s.shotClient.FetchUsers(1, 1000)
        if err != nil {
            shotErr = err
            return
        }
        shotUsers = resp.Users
    }()

    wg.Wait()

    if managerErr != nil {
        return nil, managerErr
    }
    if shotErr != nil {
        return nil, shotErr
    }

    // Merge data by user_id
    return s.mergeUsers(managerUsers, shotUsers), nil
}

func (s *UserService) mergeUsers(managerUsers []manager.User, shotUsers []shot.User) []models.User {
    userMap := make(map[string]*models.User)

    // Add manager data
    for _, mu := range managerUsers {
        userMap[mu.UserID] = &models.User{
            UserID:        mu.UserID,
            Username:      mu.Username,
            Bot:           mu.Bot,
            ManagerIsPaid: mu.ManagerIsPaid,
        }
    }

    // Merge shot data
    for _, su := range shotUsers {
        if user, exists := userMap[su.UserID]; exists {
            user.ShotIsPaid = su.ShotIsPaid
            user.MoneyManagementStatus = su.MoneyManagementStatus
            user.TradeStats = su.TradeStats
            // ... merge other fields
        } else {
            // User only exists in Shot
            userMap[su.UserID] = &models.User{
                UserID:                 su.UserID,
                ShotIsPaid:             su.ShotIsPaid,
                MoneyManagementStatus:  su.MoneyManagementStatus,
                TradeStats:             su.TradeStats,
            }
        }
    }

    // Convert map to slice
    users := make([]models.User, 0, len(userMap))
    for _, user := range userMap {
        users = append(users, *user)
    }

    return users
}

func (s *UserService) FilterUsers(users []models.User, filters map[string]string) []models.User {
    filtered := make([]models.User, 0)

    for _, user := range users {
        if !s.matchesFilters(user, filters) {
            continue
        }
        filtered = append(filtered, user)
    }

    return filtered
}

func (s *UserService) matchesFilters(user models.User, filters map[string]string) bool {
    // Manager status filter
    if manager, ok := filters["manager"]; ok && manager != "all" {
        if manager == "paid" && !user.ManagerIsPaid {
            return false
        }
        if manager == "trial" && user.ManagerIsPaid {
            return false
        }
    }

    // Shot status filter
    if shot, ok := filters["shot"]; ok && shot != "all" {
        if shot == "paid" && !user.ShotIsPaid {
            return false
        }
        if shot == "trial" && user.ShotIsPaid {
            return false
        }
    }

    // Mode filter
    if mode, ok := filters["mod"]; ok && mode != "all" {
        if user.MoneyManagementStatus != mode {
            return false
        }
    }

    // Bot filter
    if bot, ok := filters["bot"]; ok && bot != "all" {
        botNum := 0
        fmt.Sscanf(bot, "bot%d", &botNum)
        if user.Bot != botNum {
            return false
        }
    }

    return true
}
```

---

### Example 8: Dashboard Handler

**File:** `internal/handlers/dashboard.go`

```go
package handlers

import (
    "net/http"
    "signalvision-admin/internal/services"

    "github.com/gin-gonic/gin"
)

type DashboardHandler struct {
    userService  *services.UserService
    statsService *services.StatsService
}

func NewDashboardHandler(userService *services.UserService, statsService *services.StatsService) *DashboardHandler {
    return &DashboardHandler{
        userService:  userService,
        statsService: statsService,
    }
}

func (h *DashboardHandler) Index(c *gin.Context) {
    // Fetch recent users
    recentUsers, err := h.userService.FetchRecentUsers(5)
    if err != nil {
        c.HTML(http.StatusInternalServerError, "error.html", gin.H{
            "error": "Failed to fetch users",
        })
        return
    }

    // Fetch statistics
    stats, err := h.statsService.GetDashboardStats()
    if err != nil {
        c.HTML(http.StatusInternalServerError, "error.html", gin.H{
            "error": "Failed to fetch statistics",
        })
        return
    }

    c.HTML(http.StatusOK, "dashboard/index.html", gin.H{
        "user":        c.MustGet("user"),
        "users":       recentUsers,
        "total_users": stats.TotalUsers,
        "paid_users":  stats.PaidUsers,
        "free_users":  stats.FreeUsers,
        "trades":      stats.Trades,
    })
}
```

---

### Example 9: Telegram Client

**File:** `internal/api/telegram/client.go`

```go
package telegram

import (
    "fmt"
    "github.com/go-resty/resty/v2"
)

type Client struct {
    botToken string
    http     *resty.Client
}

type SendMessageRequest struct {
    ChatID    string `json:"chat_id"`
    Text      string `json:"text"`
    ParseMode string `json:"parse_mode"`
}

type SendMessageResponse struct {
    Ok     bool   `json:"ok"`
    Result Result `json:"result,omitempty"`
}

type Result struct {
    MessageID int `json:"message_id"`
}

func NewClient(botToken string) *Client {
    return &Client{
        botToken: botToken,
        http:     resty.New(),
    }
}

func (c *Client) SendMessage(chatID, message string) error {
    url := fmt.Sprintf("https://api.telegram.org/bot%s/sendMessage", c.botToken)

    var result SendMessageResponse

    resp, err := c.http.R().
        SetBody(SendMessageRequest{
            ChatID:    chatID,
            Text:      message,
            ParseMode: "HTML",
        }).
        SetResult(&result).
        Post(url)

    if err != nil {
        return fmt.Errorf("request failed: %w", err)
    }

    if resp.StatusCode() != 200 {
        return fmt.Errorf("API returned status %d", resp.StatusCode())
    }

    if !result.Ok {
        return fmt.Errorf("telegram API returned ok=false")
    }

    return nil
}
```

---

### Example 10: Dockerfile

**File:** `Dockerfile`

```dockerfile
# Build stage
FROM golang:1.21-alpine AS builder

# Install build dependencies
RUN apk add --no-cache git

# Set working directory
WORKDIR /app

# Copy go mod files
COPY go.mod go.sum ./

# Download dependencies
RUN go mod download

# Copy source code
COPY . .

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o server cmd/server/main.go

# Runtime stage
FROM alpine:latest

# Install CA certificates for HTTPS
RUN apk --no-cache add ca-certificates

# Create app directory
WORKDIR /root/

# Copy binary from builder
COPY --from=builder /app/server .

# Copy web assets
COPY --from=builder /app/web ./web

# Copy migrations (optional)
COPY --from=builder /app/migrations ./migrations

# Expose port
EXPOSE 8080

# Set environment
ENV GIN_MODE=release

# Run the application
CMD ["./server"]
```

---

### Example 11: Docker Compose

**File:** `docker-compose.yml`

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:8080"
    environment:
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=admin
      - DB_USERNAME=admin
      - DB_PASSWORD=Q1m0DeTvFbmpqKgy2gyE
      - MANAGER_API_END_POINT=https://manager.signalvision.ai
      - SHOT_API_END_POINT=https://trader.signalvision.ai
      - API_SECRET=TRT56WTWRT
      - SUPPORT_BOT_TOKEN=7898051144:AAEQPhfIGOJ7LnDOqMTJxf6MW-AEVmyKzCM
      - SESSION_SECRET=your-secret-key-here
    depends_on:
      - db
    networks:
      - app-network

  db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=rootpassword
      - MYSQL_DATABASE=admin
      - MYSQL_USER=admin
      - MYSQL_PASSWORD=Q1m0DeTvFbmpqKgy2gyE
    ports:
      - "3306:3306"
    volumes:
      - db-data:/var/lib/mysql
    networks:
      - app-network

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    networks:
      - app-network

volumes:
  db-data:

networks:
  app-network:
    driver: bridge
```

---

### Example 12: Makefile

**File:** `Makefile`

```makefile
.PHONY: build run test clean docker-build docker-run migrate

# Build the application
build:
	go build -o bin/server cmd/server/main.go

# Run the application
run:
	go run cmd/server/main.go

# Run tests
test:
	go test -v -cover ./...

# Clean build artifacts
clean:
	rm -rf bin/

# Build Docker image
docker-build:
	docker build -t signalvision-admin:latest .

# Run Docker container
docker-run:
	docker-compose up -d

# Stop Docker containers
docker-stop:
	docker-compose down

# Database migration
migrate:
	mysql -h127.0.0.1 -uadmin -p < migrations/001_create_admin_users.sql

# Install dependencies
deps:
	go mod download
	go mod tidy

# Format code
fmt:
	go fmt ./...

# Lint code
lint:
	golangci-lint run

# Hot reload for development
dev:
	air
```

---

## Final Notes

### Current Laravel Application Characteristics

**Strengths:**
- Clean MVC architecture
- Simple database schema
- Well-organized routes and controllers
- Modern frontend with good UX
- Clear separation of concerns

**Challenges for Migration:**
- Heavy reliance on external APIs (not under your control)
- Client-side data filtering (inefficient for large datasets)
- No caching layer
- Hardcoded credentials in `.env` (security risk)
- Limited error handling and logging

### Estimated Migration Timeline

**For a competent Go developer:**
- **Weeks 1-2:** Backend implementation (auth, handlers, API clients)
- **Weeks 3-4:** Frontend templates and integration
- **Week 5:** Optimization, caching, testing
- **Week 6:** Deployment, monitoring, documentation

**Total: 4-6 weeks**

### Primary Benefits of Go Migration

1. **Performance:** 10-100x faster than PHP
2. **Cost:** Lower hosting costs due to efficiency
3. **Deployment:** Single binary, no dependencies
4. **Scalability:** Better concurrency handling
5. **Maintainability:** Strong typing, better tooling

### Recommended Next Steps

1. **Review this documentation thoroughly**
2. **Set up local Go development environment**
3. **Start with Phase 1: Project setup**
4. **Implement authentication first** (critical path)
5. **Build iteratively, test frequently**
6. **Deploy to staging before production**

---

**Document Version:** 1.0
**Last Updated:** December 14, 2025
**Prepared For:** SignalVision Admin Go Migration

---

For questions or clarifications, refer to:
- [Go Documentation](https://golang.org/doc/)
- [Gin Framework](https://gin-gonic.com/docs/)
- [GORM Documentation](https://gorm.io/docs/)
