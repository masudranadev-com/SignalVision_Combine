# Standard Go Project Structure - SignalVision Trader

## Complete Folder Structure

```
trader-go/
│
├── cmd/
│   ├── api/
│   │   └── main.go                    # API server entry point
│   ├── worker/
│   │   └── main.go                    # Background worker entry point
│   └── migrate/
│       └── main.go                    # Database migration tool
│
├── internal/
│   ├── api/
│   │   ├── handlers/
│   │   │   ├── binance_handler.go
│   │   │   ├── bybit_handler.go
│   │   │   ├── telegram_handler.go
│   │   │   ├── license_handler.go
│   │   │   ├── money_management_handler.go
│   │   │   └── crypto_price_handler.go
│   │   │
│   │   ├── middleware/
│   │   │   ├── auth.go
│   │   │   ├── api_secret.go
│   │   │   ├── cors.go
│   │   │   ├── logger.go
│   │   │   ├── rate_limit.go
│   │   │   └── recovery.go
│   │   │
│   │   └── routes/
│   │       ├── routes.go
│   │       ├── api.go
│   │       └── web.go
│   │
│   ├── domain/
│   │   ├── models/
│   │   │   ├── telegram_user.go
│   │   │   ├── subscription.go
│   │   │   ├── user.go
│   │   │   └── trade.go
│   │   │
│   │   ├── repository/
│   │   │   ├── telegram_user_repository.go
│   │   │   ├── subscription_repository.go
│   │   │   └── repository.go
│   │   │
│   │   └── dto/
│   │       ├── request/
│   │       │   ├── binance_request.go
│   │       │   ├── bybit_request.go
│   │       │   ├── license_request.go
│   │       │   └── money_management_request.go
│   │       │
│   │       └── response/
│   │           ├── binance_response.go
│   │           ├── bybit_response.go
│   │           ├── license_response.go
│   │           └── api_response.go
│   │
│   ├── service/
│   │   ├── binance/
│   │   │   ├── binance_service.go
│   │   │   ├── order_service.go
│   │   │   ├── position_service.go
│   │   │   └── wallet_service.go
│   │   │
│   │   ├── bybit/
│   │   │   └── bybit_service.go
│   │   │
│   │   ├── telegram/
│   │   │   ├── telegram_service.go
│   │   │   ├── message_handler.go
│   │   │   ├── callback_handler.go
│   │   │   └── state_machine.go
│   │   │
│   │   ├── license/
│   │   │   └── license_service.go
│   │   │
│   │   ├── money_management/
│   │   │   ├── money_management_service.go
│   │   │   ├── risk_calculator.go
│   │   │   └── portfolio_manager.go
│   │   │
│   │   └── crypto_price/
│   │       └── price_service.go
│   │
│   ├── database/
│   │   ├── database.go
│   │   ├── migrations/
│   │   │   ├── 001_create_users_table.go
│   │   │   ├── 002_create_telegram_users_table.go
│   │   │   ├── 003_create_subscriptions_table.go
│   │   │   └── migration.go
│   │   │
│   │   └── seeders/
│   │       └── seeder.go
│   │
│   └── utils/
│       ├── crypto/
│       │   ├── hmac.go
│       │   └── signature.go
│       │
│       ├── formatter/
│       │   ├── number_formatter.go
│       │   └── date_formatter.go
│       │
│       ├── validator/
│       │   └── validator.go
│       │
│       └── helpers/
│           ├── response.go
│           └── error.go
│
├── pkg/
│   ├── config/
│   │   └── config.go
│   │
│   ├── logger/
│   │   └── logger.go
│   │
│   ├── httputil/
│   │   └── client.go
│   │
│   └── cache/
│       ├── redis.go
│       └── memory.go
│
├── api/
│   ├── openapi/
│   │   └── swagger.yaml
│   │
│   └── proto/
│       └── (gRPC definitions if needed)
│
├── scripts/
│   ├── build.sh
│   ├── deploy.sh
│   ├── migrate.sh
│   └── test.sh
│
├── deployments/
│   ├── docker/
│   │   ├── Dockerfile
│   │   ├── Dockerfile.dev
│   │   └── .dockerignore
│   │
│   ├── kubernetes/
│   │   ├── deployment.yaml
│   │   ├── service.yaml
│   │   ├── configmap.yaml
│   │   └── secrets.yaml
│   │
│   └── docker-compose.yml
│
├── configs/
│   ├── config.yaml
│   ├── config.dev.yaml
│   ├── config.prod.yaml
│   └── .env.example
│
├── tests/
│   ├── unit/
│   │   ├── services/
│   │   │   ├── binance_service_test.go
│   │   │   └── telegram_service_test.go
│   │   │
│   │   └── utils/
│   │       └── crypto_test.go
│   │
│   ├── integration/
│   │   ├── api/
│   │   │   ├── binance_test.go
│   │   │   └── telegram_test.go
│   │   │
│   │   └── database/
│   │       └── repository_test.go
│   │
│   ├── e2e/
│   │   └── trading_flow_test.go
│   │
│   ├── fixtures/
│   │   ├── telegram_users.json
│   │   └── subscriptions.json
│   │
│   └── mocks/
│       ├── mock_binance_service.go
│       ├── mock_telegram_service.go
│       └── mock_repository.go
│
├── docs/
│   ├── README.md
│   ├── API.md
│   ├── ARCHITECTURE.md
│   ├── DEPLOYMENT.md
│   └── CONTRIBUTING.md
│
├── tools/
│   └── tools.go
│
├── web/
│   └── (optional frontend if needed)
│
├── .github/
│   └── workflows/
│       ├── ci.yml
│       ├── cd.yml
│       └── tests.yml
│
├── .gitignore
├── .env
├── .env.example
├── go.mod
├── go.sum
├── Makefile
├── README.md
├── LICENSE
└── CHANGELOG.md
```

---

## Directory Explanations

### `/cmd/` - Application Entry Points
Main applications for this project. The directory name for each application matches the name of the executable.

- **`cmd/api/`** - Main API server
- **`cmd/worker/`** - Background job processor
- **`cmd/migrate/`** - Database migration tool

### `/internal/` - Private Application Code
Code that you don't want others importing in their applications or libraries.

#### `/internal/api/` - API Layer
- **`handlers/`** - HTTP request handlers (controllers)
- **`middleware/`** - HTTP middleware functions
- **`routes/`** - Route definitions

#### `/internal/domain/` - Business Domain Layer
- **`models/`** - Database models (entities)
- **`repository/`** - Database access layer (data access)
- **`dto/`** - Data Transfer Objects (request/response structures)

#### `/internal/service/` - Business Logic Layer
- Service implementations
- Business logic
- External API integrations

#### `/internal/database/` - Database Layer
- Database connection
- Migrations
- Seeders

#### `/internal/utils/` - Internal Utilities
- Helper functions
- Formatters
- Validators

### `/pkg/` - Public Library Code
Code that's okay to use by external applications. Other projects will import these libraries.

- **`config/`** - Configuration management
- **`logger/`** - Logging utilities
- **`httputil/`** - HTTP utilities
- **`cache/`** - Caching utilities

### `/api/` - API Definitions
- OpenAPI/Swagger specs
- Protocol definition files
- JSON schema files

### `/scripts/` - Scripts
Scripts to perform various build, install, analysis operations.

### `/deployments/` - Deployment Configurations
- Docker files
- Kubernetes manifests
- docker-compose

### `/configs/` - Configuration Files
Configuration file templates or default configs.

### `/tests/` - Additional Test Applications
- Unit tests
- Integration tests
- E2E tests
- Test fixtures
- Mocks

### `/docs/` - Documentation
Design and user documents.

### `/tools/` - Supporting Tools
Supporting tools for this project.

### `/web/` - Web Application Assets
Web application specific components: static files, templates, etc.

---

## File Structure Examples

### Main Entry Point: `cmd/api/main.go`

```go
package main

import (
    "context"
    "fmt"
    "log"
    "net/http"
    "os"
    "os/signal"
    "syscall"
    "time"

    "github.com/gin-gonic/gin"
    "trader-go/internal/api/routes"
    "trader-go/internal/database"
    "trader-go/pkg/config"
    "trader-go/pkg/logger"
)

func main() {
    // Load configuration
    cfg, err := config.Load()
    if err != nil {
        log.Fatalf("Failed to load config: %v", err)
    }

    // Initialize logger
    logger.Init(cfg.Log)

    // Connect to database
    if err := database.Connect(cfg.Database); err != nil {
        logger.Fatal("Failed to connect to database", err)
    }

    // Run migrations
    if err := database.Migrate(); err != nil {
        logger.Fatal("Failed to run migrations", err)
    }

    // Setup Gin router
    if cfg.App.Env == "production" {
        gin.SetMode(gin.ReleaseMode)
    }

    router := gin.Default()
    routes.Setup(router, cfg)

    // Create HTTP server
    srv := &http.Server{
        Addr:           fmt.Sprintf(":%s", cfg.App.Port),
        Handler:        router,
        ReadTimeout:    10 * time.Second,
        WriteTimeout:   10 * time.Second,
        MaxHeaderBytes: 1 << 20,
    }

    // Start server in goroutine
    go func() {
        logger.Info(fmt.Sprintf("Starting server on port %s", cfg.App.Port))
        if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
            logger.Fatal("Failed to start server", err)
        }
    }()

    // Graceful shutdown
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
    <-quit

    logger.Info("Shutting down server...")

    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    if err := srv.Shutdown(ctx); err != nil {
        logger.Fatal("Server forced to shutdown", err)
    }

    logger.Info("Server exited")
}
```

### Handler Example: `internal/api/handlers/binance_handler.go`

```go
package handlers

import (
    "net/http"

    "github.com/gin-gonic/gin"
    "trader-go/internal/domain/dto/request"
    "trader-go/internal/domain/repository"
    "trader-go/internal/service/binance"
    "trader-go/internal/utils/helpers"
)

type BinanceHandler struct {
    binanceService *binance.Service
    userRepo       repository.TelegramUserRepository
}

func NewBinanceHandler(
    binanceService *binance.Service,
    userRepo repository.TelegramUserRepository,
) *BinanceHandler {
    return &BinanceHandler{
        binanceService: binanceService,
        userRepo:       userRepo,
    }
}

func (h *BinanceHandler) GetWalletBalance(c *gin.Context) {
    var req request.UserIDRequest
    if err := c.ShouldBindJSON(&req); err != nil {
        helpers.ErrorResponse(c, http.StatusBadRequest, "Invalid request", "VALIDATION_ERROR")
        return
    }

    user, err := h.userRepo.FindByChatID(req.UserID)
    if err != nil {
        helpers.ErrorResponse(c, http.StatusNotFound, "User not found", "USER_NOT_FOUND")
        return
    }

    if user.BinanceAPIKey == nil || user.BinanceAPISecret == nil {
        helpers.ErrorResponse(c, http.StatusBadRequest, "Binance API keys not configured", "API_KEYS_MISSING")
        return
    }

    balance, err := h.binanceService.GetWalletBalance(*user.BinanceAPIKey, *user.BinanceAPISecret)
    if err != nil {
        helpers.ErrorResponse(c, http.StatusInternalServerError, err.Error(), "API_ERROR")
        return
    }

    helpers.SuccessResponse(c, balance)
}

func (h *BinanceHandler) PlaceOrder(c *gin.Context) {
    var req request.PlaceOrderRequest
    if err := c.ShouldBindJSON(&req); err != nil {
        helpers.ErrorResponse(c, http.StatusBadRequest, "Invalid request", "VALIDATION_ERROR")
        return
    }

    user, err := h.userRepo.FindByChatID(req.UserID)
    if err != nil {
        helpers.ErrorResponse(c, http.StatusNotFound, "User not found", "USER_NOT_FOUND")
        return
    }

    if user.BinanceAPIKey == nil || user.BinanceAPISecret == nil {
        helpers.ErrorResponse(c, http.StatusBadRequest, "Binance API keys not configured", "API_KEYS_MISSING")
        return
    }

    result, err := h.binanceService.PlaceOrder(
        *user.BinanceAPIKey,
        *user.BinanceAPISecret,
        req,
    )
    if err != nil {
        helpers.ErrorResponse(c, http.StatusInternalServerError, err.Error(), "ORDER_FAILED")
        return
    }

    helpers.SuccessResponse(c, result)
}
```

### Service Example: `internal/service/binance/binance_service.go`

```go
package binance

import (
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "fmt"
    "net/url"
    "strconv"
    "time"

    "github.com/go-resty/resty/v2"
    "trader-go/internal/domain/dto/request"
    "trader-go/internal/domain/dto/response"
    "trader-go/pkg/config"
)

type Service struct {
    baseURL string
    client  *resty.Client
}

func NewService(cfg *config.Config) *Service {
    return &Service{
        baseURL: cfg.Binance.BaseURL,
        client:  resty.New().SetTimeout(10 * time.Second),
    }
}

func (s *Service) generateSignature(queryString, apiSecret string) string {
    h := hmac.New(sha256.New, []byte(apiSecret))
    h.Write([]byte(queryString))
    return hex.EncodeToString(h.Sum(nil))
}

func (s *Service) GetWalletBalance(apiKey, apiSecret string) (*response.WalletBalanceResponse, error) {
    endpoint := "/fapi/v2/balance"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.generateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var balances []map[string]interface{}
    resp, err := s.client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetQueryParamsFromValues(params).
        SetResult(&balances).
        Get(s.baseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return nil, fmt.Errorf("API error: %s", resp.String())
    }

    for _, balance := range balances {
        if balance["asset"] == "USDT" {
            return &response.WalletBalanceResponse{
                Status:           true,
                AvailableBalance: balance["availableBalance"].(string),
            }, nil
        }
    }

    return nil, fmt.Errorf("USDT balance not found")
}

func (s *Service) PlaceOrder(apiKey, apiSecret string, req request.PlaceOrderRequest) (*response.OrderResponse, error) {
    // Implementation here
    return nil, nil
}
```

### Repository Example: `internal/domain/repository/telegram_user_repository.go`

```go
package repository

import (
    "gorm.io/gorm"
    "trader-go/internal/domain/models"
)

type TelegramUserRepository interface {
    FindByChatID(chatID string) (*models.TelegramUser, error)
    Create(user *models.TelegramUser) error
    Update(user *models.TelegramUser) error
    Delete(chatID string) error
    FirstOrCreate(chatID string) (*models.TelegramUser, error)
}

type telegramUserRepository struct {
    db *gorm.DB
}

func NewTelegramUserRepository(db *gorm.DB) TelegramUserRepository {
    return &telegramUserRepository{db: db}
}

func (r *telegramUserRepository) FindByChatID(chatID string) (*models.TelegramUser, error) {
    var user models.TelegramUser
    if err := r.db.Where("chat_id = ?", chatID).First(&user).Error; err != nil {
        return nil, err
    }
    return &user, nil
}

func (r *telegramUserRepository) Create(user *models.TelegramUser) error {
    return r.db.Create(user).Error
}

func (r *telegramUserRepository) Update(user *models.TelegramUser) error {
    return r.db.Save(user).Error
}

func (r *telegramUserRepository) Delete(chatID string) error {
    return r.db.Where("chat_id = ?", chatID).Delete(&models.TelegramUser{}).Error
}

func (r *telegramUserRepository) FirstOrCreate(chatID string) (*models.TelegramUser, error) {
    var user models.TelegramUser
    result := r.db.Where("chat_id = ?", chatID).FirstOrCreate(&user)
    if result.Error != nil {
        return nil, result.Error
    }
    return &user, nil
}
```

### Config Example: `pkg/config/config.go`

```go
package config

import (
    "github.com/spf13/viper"
)

type Config struct {
    App      AppConfig
    Database DatabaseConfig
    Binance  BinanceConfig
    Bybit    BybitConfig
    Telegram TelegramConfig
    Redis    RedisConfig
    Log      LogConfig
}

type AppConfig struct {
    Name string
    Env  string
    Port string
}

type DatabaseConfig struct {
    Host     string
    Port     string
    Database string
    Username string
    Password string
}

type BinanceConfig struct {
    BaseURL string
}

type BybitConfig struct {
    BaseURL string
}

type TelegramConfig struct {
    BotToken string
}

type RedisConfig struct {
    Host     string
    Port     string
    Password string
    DB       int
}

type LogConfig struct {
    Level  string
    Format string
}

func Load() (*Config, error) {
    viper.SetConfigFile(".env")
    viper.AutomaticEnv()

    if err := viper.ReadInConfig(); err != nil {
        return nil, err
    }

    config := &Config{
        App: AppConfig{
            Name: viper.GetString("APP_NAME"),
            Env:  viper.GetString("APP_ENV"),
            Port: viper.GetString("APP_PORT"),
        },
        Database: DatabaseConfig{
            Host:     viper.GetString("DB_HOST"),
            Port:     viper.GetString("DB_PORT"),
            Database: viper.GetString("DB_DATABASE"),
            Username: viper.GetString("DB_USERNAME"),
            Password: viper.GetString("DB_PASSWORD"),
        },
        Binance: BinanceConfig{
            BaseURL: viper.GetString("BINANCE_BASE_URL"),
        },
        Bybit: BybitConfig{
            BaseURL: viper.GetString("BYBIT_BASE_URL"),
        },
        Telegram: TelegramConfig{
            BotToken: viper.GetString("TELEGRAM_BOT_TOKEN"),
        },
        Redis: RedisConfig{
            Host:     viper.GetString("REDIS_HOST"),
            Port:     viper.GetString("REDIS_PORT"),
            Password: viper.GetString("REDIS_PASSWORD"),
            DB:       viper.GetInt("REDIS_DB"),
        },
        Log: LogConfig{
            Level:  viper.GetString("LOG_LEVEL"),
            Format: viper.GetString("LOG_FORMAT"),
        },
    }

    return config, nil
}
```

### Makefile

```makefile
.PHONY: help build run test clean docker-build docker-run migrate

help: ## Show this help
    @grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Build the application
    @echo "Building..."
    @go build -o bin/api cmd/api/main.go
    @go build -o bin/worker cmd/worker/main.go
    @go build -o bin/migrate cmd/migrate/main.go

run: ## Run the application
    @go run cmd/api/main.go

test: ## Run tests
    @go test -v -cover ./...

test-coverage: ## Run tests with coverage
    @go test -v -coverprofile=coverage.out ./...
    @go tool cover -html=coverage.out

clean: ## Clean build files
    @rm -rf bin/
    @rm -f coverage.out

deps: ## Download dependencies
    @go mod download
    @go mod tidy

lint: ## Run linter
    @golangci-lint run

docker-build: ## Build docker image
    @docker build -t trader-go:latest -f deployments/docker/Dockerfile .

docker-run: ## Run docker container
    @docker-compose -f deployments/docker-compose.yml up

migrate-up: ## Run database migrations
    @go run cmd/migrate/main.go up

migrate-down: ## Rollback database migrations
    @go run cmd/migrate/main.go down

migrate-create: ## Create new migration file
    @read -p "Enter migration name: " name; \
    go run cmd/migrate/main.go create $$name

dev: ## Run in development mode with hot reload
    @air

.DEFAULT_GOAL := help
```

---

## Quick Start Commands

```bash
# Initialize project
mkdir trader-go && cd trader-go
go mod init trader-go

# Install dependencies
make deps

# Create necessary directories
mkdir -p cmd/api cmd/worker cmd/migrate
mkdir -p internal/api/{handlers,middleware,routes}
mkdir -p internal/domain/{models,repository,dto/request,dto/response}
mkdir -p internal/service/{binance,bybit,telegram,license,money_management,crypto_price}
mkdir -p internal/database/migrations
mkdir -p internal/utils/{crypto,formatter,validator,helpers}
mkdir -p pkg/{config,logger,httputil,cache}
mkdir -p tests/{unit,integration,e2e,fixtures,mocks}
mkdir -p configs deployments/docker scripts docs

# Build application
make build

# Run application
make run

# Run tests
make test
```

---

## Best Practices

1. **Separation of Concerns**: Keep handlers, services, and repositories separate
2. **Dependency Injection**: Use constructor injection for dependencies
3. **Interface-Based Design**: Define interfaces for services and repositories
4. **Error Handling**: Consistent error handling across layers
5. **Testing**: Write unit tests for services, integration tests for APIs
6. **Logging**: Structured logging with context
7. **Configuration**: Environment-based configuration
8. **Documentation**: Comment exported functions and types
9. **Clean Code**: Follow Go conventions and style guides
10. **Security**: Never commit secrets, use environment variables

This structure follows the **Standard Go Project Layout** and is suitable for production applications!
