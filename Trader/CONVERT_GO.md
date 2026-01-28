# Laravel to Go Conversion Guide - SignalVision Trader

## Table of Contents
1. [Project Overview](#project-overview)
2. [Current Architecture Analysis](#current-architecture-analysis)
3. [Go Architecture Design](#go-architecture-design)
4. [Technology Stack Mapping](#technology-stack-mapping)
5. [Database Migration](#database-migration)
6. [API Conversion Guide](#api-conversion-guide)
7. [Service Layer Conversion](#service-layer-conversion)
8. [External Integrations](#external-integrations)
9. [Project Structure](#project-structure)
10. [Implementation Roadmap](#implementation-roadmap)
11. [Code Examples](#code-examples)
12. [Testing Strategy](#testing-strategy)
13. [Deployment Considerations](#deployment-considerations)

---

## Project Overview

### Current Application: Laravel PHP Cryptocurrency Trading Bot
- **Framework**: Laravel 10.x (PHP 8.1+)
- **Primary Function**: Telegram bot for managing cryptocurrency trades on Binance and Bybit
- **Key Features**:
  - Trading signal management via Telegram
  - Binance Futures API integration
  - Bybit API integration
  - License/subscription management
  - Money management and risk calculation
  - Multi-user support with API key storage

---

## Current Architecture Analysis

### Backend Components

#### 1. **Controllers**
- `TelegramBotController.php` - Handles Telegram webhook callbacks and user interactions
- `CryptoApiBinance.php` - Binance trading operations
- `CryptoApiBybit.php` - Bybit trading operations (proxy to external service)
- `MoneyManagementController.php` - Risk management and wallet tracking
- `SignalTraderLicense.php` - License validation
- `CryptoPrice.php` - Cryptocurrency price data

#### 2. **Services**
- `BinanceService.php` - Direct Binance Futures API integration
  - Wallet balance retrieval
  - Order placement (limit/market)
  - Position management
  - Stop-loss/Take-profit orders
  - Leverage configuration

#### 3. **Models**
- `TelegramUser` - User chat data and API keys
- `Subscription` - License and subscription tracking
- `User` - Laravel default user model

#### 4. **Database Schema**
```
telegram_users:
  - id, chat_id, state, subscription_type
  - activation_in, expired_in
  - api_key, api_secret
  - money_management fields (wallet_balance, risk, etc.)

subscriptions:
  - id, user_id, license, name, email
  - web_user_id, product_id
  - start_date, next_date, package_type
```

#### 5. **External Dependencies**
- `binance/binance-connector-php` - Binance API client
- `irazasyed/telegram-bot-sdk` - Telegram bot framework
- Guzzle HTTP client

#### 6. **Routes**
- **API Routes**: `/api/bybit/*`, `/api/binance/*`, `/api/license/*`, `/api/money-management/*`
- **Web Routes**: Telegram webhook, crypto price endpoints

---

## Go Architecture Design

### Recommended Framework & Libraries

#### Web Framework
**Gin** (recommended) or **Fiber**
```go
// Gin - Fast HTTP framework
github.com/gin-gonic/gin

// OR Fiber - Express-like framework
github.com/gofiber/fiber/v2
```

#### Database ORM
**GORM** - Go's most popular ORM
```go
gorm.io/gorm
gorm.io/driver/mysql
```

#### HTTP Client
**Resty** - Simple HTTP client
```go
github.com/go-resty/resty/v2
```

#### Telegram Bot SDK
**Telebot** or **go-telegram-bot-api**
```go
gopkg.in/telebot.v3
// OR
github.com/go-telegram-bot-api/telegram-bot-api/v5
```

#### Configuration
**Viper** - Configuration management
```go
github.com/spf13/viper
```

#### Logging
**Zap** or **Logrus**
```go
go.uber.org/zap
// OR
github.com/sirupsen/logrus
```

#### Crypto/HMAC
Standard library `crypto/hmac` and `crypto/sha256`

---

## Technology Stack Mapping

| Laravel Component | Go Replacement |
|------------------|----------------|
| Laravel Framework | Gin / Fiber |
| Eloquent ORM | GORM |
| Laravel Sanctum | JWT-go / custom middleware |
| Guzzle HTTP | Resty / net/http |
| Telegram Bot SDK | Telebot / telegram-bot-api |
| Laravel Config | Viper |
| Laravel Logging | Zap / Logrus |
| Artisan Commands | Cobra CLI |
| Laravel Queue | Go channels / Redis queue |
| Laravel Cache | go-redis / go-cache |
| Carbon (dates) | Standard time package |

---

## Database Migration

### Step 1: GORM Models

```go
package models

import (
    "time"
    "gorm.io/gorm"
)

type TelegramUser struct {
    ID                                        uint      `gorm:"primaryKey"`
    ChatID                                    int64     `gorm:"index"`
    State                                     *string
    SubscriptionType                          *string
    ActivationIn                              *string
    ExpiredIn                                 *string

    // Binance API credentials
    BinanceAPIKey                             *string
    BinanceAPISecret                          *string

    // Bybit API credentials
    BybitAPIKey                               *string
    BybitAPISecret                            *string

    // Money Management
    MoneyManagementStatus                     string  `gorm:"default:'inactive'"`
    MoneyManagementType                       string
    MoneyManagementRisk                       float64
    MoneyManagementBybitWalletBalance         float64
    MoneyManagementBinanceWalletBalance       float64
    MoneyManagementDemoWalletBalance          float64
    MoneyManagementDemoAvailableBalance       float64
    MoneyManagementMaxExposure                float64
    MoneyManagementTradeLimit                 int
    MoneyManagementDailyLoss                  float64
    MoneyManagementStopTrades                 bool

    // Status fields
    MoneyManagementStatusMaxExposure          string `gorm:"default:'inactive'"`
    MoneyManagementStatusTradeLimit           string `gorm:"default:'inactive'"`
    MoneyManagementStatusDailyLoss            string `gorm:"default:'inactive'"`
    MoneyManagementStatusStopTrades           string `gorm:"default:'inactive'"`

    // Universal settings
    MoneyManagementUniLeverageStatus          string `gorm:"default:'inactive'"`
    MoneyManagementUniLeverage                int
    MoneyManagementUniStrategyStatus          string
    MoneyManagementExchange                   string
    MoneyManagementTradesMode                 string
    MoneyManagementProfitStrategy             string
    MoneyManagementProfitStrategyTP           string
    MoneyManagementProfitStrategyPartial      string

    CreatedAt                                 time.Time
    UpdatedAt                                 time.Time
}

type Subscription struct {
    ID          uint      `gorm:"primaryKey"`
    UserID      string
    License     string    `gorm:"index"`
    Name        string
    Email       string
    WebUserID   string
    ProductID   string
    StartDate   string
    NextDate    string
    PackageType string
    CreatedAt   time.Time
    UpdatedAt   time.Time
}
```

### Step 2: Database Connection

```go
package database

import (
    "fmt"
    "log"
    "gorm.io/driver/mysql"
    "gorm.io/gorm"
    "github.com/spf13/viper"
)

var DB *gorm.DB

func Connect() {
    dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
        viper.GetString("DB_USERNAME"),
        viper.GetString("DB_PASSWORD"),
        viper.GetString("DB_HOST"),
        viper.GetString("DB_PORT"),
        viper.GetString("DB_DATABASE"),
    )

    var err error
    DB, err = gorm.Open(mysql.Open(dsn), &gorm.Config{})
    if err != nil {
        log.Fatal("Failed to connect to database:", err)
    }

    // Auto-migrate models
    DB.AutoMigrate(&TelegramUser{}, &Subscription{})
}
```

### Step 3: Migration SQL (if needed)

The existing MySQL database can be used directly. GORM will auto-migrate missing columns.

---

## API Conversion Guide

### Route Structure

```go
package routes

import (
    "github.com/gin-gonic/gin"
    "trader/controllers"
    "trader/middleware"
)

func SetupRoutes(r *gin.Engine) {
    // Web routes
    r.GET("/crypto", controllers.GetCryptoPrice)
    r.GET("/instruments-price", controllers.GetInstrumentsPrice)
    r.GET("/instruments-info", controllers.GetInstrumentsInfo)
    r.Any("/telegram-message-webhook", controllers.TelegramWebhook)

    api := r.Group("/api")
    // Optional: Add API secret middleware
    // api.Use(middleware.CheckAPISecret())
    {
        // Bybit routes
        bybit := api.Group("/bybit")
        {
            bybit.POST("/wallet", controllers.BybitWallet)
            bybit.POST("/orders/smart", controllers.BybitSmartOrder)
            bybit.POST("/orders/market", controllers.BybitPlaceMarket)
            bybit.POST("/orders/partial", controllers.BybitOpenPartialOrder)
            bybit.POST("/position/partial-close", controllers.BybitPartialClose)
            bybit.POST("/order/close", controllers.BybitCloseOrder)
            bybit.POST("/update/tp-sl", controllers.BybitUpdateTPSL)
            bybit.POST("/positions/tp-sl", controllers.BybitUpdatePositionTPSL)
            bybit.POST("/positions-order/lists/:user_id", controllers.BybitPositionOrderLists)
        }

        // Binance routes
        binance := api.Group("/binance")
        {
            binance.GET("/test", controllers.BinanceTest)
            binance.POST("/wallet-balance", controllers.BinanceWalletBalance)
            binance.POST("/open-trade", controllers.BinancePlaceOrder)
            binance.POST("/market-entry", controllers.BinanceMarketEntry)
            binance.POST("/position-status", controllers.BinancePositionStatus)
            binance.POST("/open-partial-trade", controllers.BinanceOpenPartialTrade)
            binance.POST("/close-trade", controllers.BinanceCancelOrder)
            binance.POST("/close-position", controllers.BinanceClosePosition)
            binance.POST("/list-position", controllers.BinanceListPosition)
            binance.POST("/update-trade-tp-sl", controllers.BinanceUpdateTradeTPStop)
        }

        // License routes
        license := api.Group("/license")
        {
            license.POST("/validation", controllers.LicenseValidation)
            license.POST("/status", controllers.LicenseStatus)
        }

        // Money management routes
        mm := api.Group("/money-management")
        {
            mm.POST("/info", controllers.MoneyManagementInfo)
            mm.POST("/get-risk", controllers.GetRisk)
            mm.POST("/update-config", controllers.UpdateConfig)
            mm.POST("/demo-balance-update", controllers.DemoBalanceUpdate)
            mm.GET("/uni-strategy", controllers.UniStrategy)
        }
    }
}
```

---

## Service Layer Conversion

### Binance Service Implementation

```go
package services

import (
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "fmt"
    "net/url"
    "strconv"
    "time"

    "github.com/go-resty/resty/v2"
)

type BinanceService struct {
    BaseURL string
    Client  *resty.Client
}

func NewBinanceService() *BinanceService {
    return &BinanceService{
        BaseURL: "https://testnet.binancefuture.com",
        Client:  resty.New(),
    }
}

// Generate HMAC SHA256 signature
func (s *BinanceService) GenerateSignature(queryString, apiSecret string) string {
    h := hmac.New(sha256.New, []byte(apiSecret))
    h.Write([]byte(queryString))
    return hex.EncodeToString(h.Sum(nil))
}

// Get wallet balance
func (s *BinanceService) GetWalletBalance(apiKey, apiSecret string) (map[string]interface{}, error) {
    endpoint := "/fapi/v2/balance"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result []map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetQueryParamsFromValues(params).
        SetResult(&result).
        Get(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "status": false,
            "error":  resp.String(),
        }, nil
    }

    // Find USDT balance
    for _, balance := range result {
        if balance["asset"] == "USDT" {
            return map[string]interface{}{
                "status":            true,
                "available_balance": balance["availableBalance"],
            }, nil
        }
    }

    return map[string]interface{}{
        "status": false,
        "error":  "USDT balance not found",
    }, nil
}

// Place limit order
func (s *BinanceService) PlaceLimitOrder(symbol, side string, quantity, price float64, apiKey, apiSecret string) (map[string]interface{}, error) {
    endpoint := "/fapi/v1/order"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("symbol", symbol)
    params.Set("side", side)
    params.Set("type", "LIMIT")
    params.Set("timeInForce", "GTC")
    params.Set("quantity", fmt.Sprintf("%.8f", quantity))
    params.Set("price", fmt.Sprintf("%.2f", price))
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetHeader("Content-Type", "application/x-www-form-urlencoded").
        SetFormDataFromValues(params).
        SetResult(&result).
        Post(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "success": false,
            "error":   result,
            "status":  resp.StatusCode(),
        }, nil
    }

    return map[string]interface{}{
        "success": true,
        "data":    result,
    }, nil
}

// Set leverage
func (s *BinanceService) SetLeverage(symbol string, leverage int, apiKey, apiSecret string) (map[string]interface{}, error) {
    endpoint := "/fapi/v1/leverage"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("symbol", symbol)
    params.Set("leverage", strconv.Itoa(leverage))
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetHeader("Content-Type", "application/x-www-form-urlencoded").
        SetFormDataFromValues(params).
        SetResult(&result).
        Post(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "success": false,
            "error":   result,
        }, nil
    }

    return map[string]interface{}{
        "success": true,
        "data":    result,
    }, nil
}

// Place market order
func (s *BinanceService) PlaceMarketOrder(symbol, side string, quantity float64, apiKey, apiSecret string) (map[string]interface{}, error) {
    endpoint := "/fapi/v1/order"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("symbol", symbol)
    params.Set("side", side)
    params.Set("type", "MARKET")
    params.Set("quantity", fmt.Sprintf("%.8f", quantity))
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetHeader("Content-Type", "application/x-www-form-urlencoded").
        SetFormDataFromValues(params).
        SetResult(&result).
        Post(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "success": false,
            "error":   result,
        }, nil
    }

    return map[string]interface{}{
        "success": true,
        "data":    result,
    }, nil
}

// Get position info
func (s *BinanceService) GetPositionInfo(symbol, apiKey, apiSecret string) (map[string]interface{}, error) {
    endpoint := "/fapi/v2/positionRisk"

    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("symbol", symbol)
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result []map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetQueryParamsFromValues(params).
        SetResult(&result).
        Get(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "success": false,
            "error":   resp.String(),
        }, nil
    }

    // Find position for symbol
    for _, pos := range result {
        if pos["symbol"] == symbol {
            return map[string]interface{}{
                "success": true,
                "data":    pos,
            }, nil
        }
    }

    return map[string]interface{}{
        "success": true,
        "data":    map[string]interface{}{},
    }, nil
}

// Close position
func (s *BinanceService) ClosePosition(symbol, apiKey, apiSecret string) (map[string]interface{}, error) {
    // Get position info first
    positionResult, err := s.GetPositionInfo(symbol, apiKey, apiSecret)
    if err != nil {
        return nil, err
    }

    if !positionResult["success"].(bool) {
        return map[string]interface{}{
            "success": false,
            "error":   "Failed to get position info",
        }, nil
    }

    posData := positionResult["data"].(map[string]interface{})
    if len(posData) == 0 {
        return map[string]interface{}{
            "success": false,
            "error":   "No open position found",
        }, nil
    }

    positionAmt, _ := strconv.ParseFloat(posData["positionAmt"].(string), 64)
    if positionAmt == 0 {
        return map[string]interface{}{
            "success": false,
            "error":   "No open position to close",
        }, nil
    }

    // Determine side and quantity
    side := "SELL"
    if positionAmt < 0 {
        side = "BUY"
    }
    quantity := positionAmt
    if quantity < 0 {
        quantity = -quantity
    }

    // Place market order to close
    endpoint := "/fapi/v1/order"
    timestamp := time.Now().UnixMilli()
    params := url.Values{}
    params.Set("symbol", symbol)
    params.Set("side", side)
    params.Set("type", "MARKET")
    params.Set("quantity", fmt.Sprintf("%.8f", quantity))
    params.Set("reduceOnly", "true")
    params.Set("recvWindow", "5000")
    params.Set("timestamp", strconv.FormatInt(timestamp, 10))

    queryString := params.Encode()
    signature := s.GenerateSignature(queryString, apiSecret)
    params.Set("signature", signature)

    var result map[string]interface{}
    resp, err := s.Client.R().
        SetHeader("X-MBX-APIKEY", apiKey).
        SetHeader("Content-Type", "application/x-www-form-urlencoded").
        SetFormDataFromValues(params).
        SetResult(&result).
        Post(s.BaseURL + endpoint)

    if err != nil {
        return nil, err
    }

    if !resp.IsSuccess() {
        return map[string]interface{}{
            "success": false,
            "error":   result,
        }, nil
    }

    return map[string]interface{}{
        "success": true,
        "data":    result,
    }, nil
}
```

### Telegram Bot Service

```go
package services

import (
    "log"
    "github.com/go-telegram-bot-api/telegram-bot-api/v5"
    "trader/database"
    "trader/models"
)

type TelegramService struct {
    Bot *tgbotapi.BotAPI
}

func NewTelegramService(token string) (*TelegramService, error) {
    bot, err := tgbotapi.NewBotAPI(token)
    if err != nil {
        return nil, err
    }

    return &TelegramService{Bot: bot}, nil
}

func (t *TelegramService) SendMessage(chatID int64, text string) error {
    msg := tgbotapi.NewMessage(chatID, text)
    _, err := t.Bot.Send(msg)
    return err
}

func (t *TelegramService) HandleWebhook(update tgbotapi.Update) {
    // Handle callback queries (button clicks)
    if update.CallbackQuery != nil {
        t.handleCallbackQuery(update.CallbackQuery)
        return
    }

    // Handle messages
    if update.Message != nil {
        t.handleMessage(update.Message)
        return
    }
}

func (t *TelegramService) handleCallbackQuery(query *tgbotapi.CallbackQuery) {
    chatID := query.Message.Chat.ID
    data := query.Data

    // Get or create user
    var user models.TelegramUser
    database.DB.FirstOrCreate(&user, models.TelegramUser{ChatID: chatID})

    switch data {
    case "main_menu":
        user.State = nil
        database.DB.Save(&user)
        t.sendMainMenu(chatID)

    case "help":
        t.sendHelpMessage(chatID)

    // Add more callback handlers
    }

    // Answer callback query
    callback := tgbotapi.NewCallback(query.ID, "")
    t.Bot.Request(callback)
}

func (t *TelegramService) handleMessage(message *tgbotapi.Message) {
    chatID := message.Chat.ID
    text := message.Text

    // Get or create user
    var user models.TelegramUser
    database.DB.FirstOrCreate(&user, models.TelegramUser{ChatID: chatID})

    // Handle based on state or command
    if message.IsCommand() {
        t.handleCommand(chatID, message.Command())
    } else if user.State != nil {
        t.handleState(chatID, text, *user.State)
    }
}

func (t *TelegramService) sendMainMenu(chatID int64) {
    keyboard := tgbotapi.NewInlineKeyboardMarkup(
        tgbotapi.NewInlineKeyboardRow(
            tgbotapi.NewInlineKeyboardButtonData("🔑 License", "license"),
            tgbotapi.NewInlineKeyboardButtonData("🛠️ API Keys", "api_keys"),
        ),
        tgbotapi.NewInlineKeyboardRow(
            tgbotapi.NewInlineKeyboardButtonData("🆘 Help", "help"),
            tgbotapi.NewInlineKeyboardButtonData("📞 Support", "support"),
        ),
    )

    msg := tgbotapi.NewMessage(chatID, "Welcome to SignalTrader! Choose an option:")
    msg.ReplyMarkup = keyboard
    t.Bot.Send(msg)
}

func (t *TelegramService) sendHelpMessage(chatID int64) {
    helpText := `📚 *Help & Documentation*

Here's how to use SignalTrader:

1️⃣ Activate your license
2️⃣ Configure your API keys
3️⃣ Start trading!

Use the buttons below to navigate.`

    msg := tgbotapi.NewMessage(chatID, helpText)
    msg.ParseMode = "Markdown"
    t.Bot.Send(msg)
}

func (t *TelegramService) handleCommand(chatID int64, command string) {
    switch command {
    case "start":
        t.sendMainMenu(chatID)
    }
}

func (t *TelegramService) handleState(chatID int64, text, state string) {
    // Handle different states (e.g., waiting for API key input)
}
```

---

## External Integrations

### License Validation Helper

```go
package helpers

import (
    "fmt"
    "time"

    "github.com/go-resty/resty/v2"
    "trader/database"
    "trader/models"
)

type LicenseValidationResponse struct {
    Valid              bool              `json:"valid"`
    ProductID          string            `json:"product_id"`
    Name               string            `json:"name"`
    Email              string            `json:"email"`
    UserID             string            `json:"user_id"`
    StartDate          string            `json:"start_date"`
    NextDate           string            `json:"next_date"`
    VariationAttributes map[string]string `json:"variation_attributes"`
}

func ValidateLicense(license string, chatID int64) (bool, string, error) {
    // Check if license already exists
    var existingSub models.Subscription
    if err := database.DB.Where("license = ?", license).First(&existingSub).Error; err == nil {
        return false, "This license is already used!", nil
    }

    // Call external API
    client := resty.New()
    var result LicenseValidationResponse

    url := fmt.Sprintf("https://signalvision.ai/wp-json/subkey/v1/validate?key=%s&token=anytokens", license)
    resp, err := client.R().
        SetResult(&result).
        Get(url)

    if err != nil {
        return false, "", err
    }

    if !resp.IsSuccess() {
        return false, "Something went wrong. Please try again later.", nil
    }

    if !result.Valid {
        return false, "Your license is invalid!", nil
    }

    // Create/update user
    var user models.TelegramUser
    database.DB.FirstOrCreate(&user, models.TelegramUser{ChatID: chatID})
    user.ActivationIn = &result.StartDate
    user.ExpiredIn = &result.NextDate
    packageType := result.VariationAttributes["period"]
    user.SubscriptionType = &packageType
    database.DB.Save(&user)

    // Create subscription record
    sub := models.Subscription{
        UserID:      fmt.Sprintf("%d", chatID),
        License:     license,
        Name:        result.Name,
        Email:       result.Email,
        WebUserID:   result.UserID,
        ProductID:   result.ProductID,
        StartDate:   result.StartDate,
        NextDate:    result.NextDate,
        PackageType: packageType,
    }
    database.DB.Create(&sub)

    return true, "License activated successfully.", nil
}

func CheckLicenseStatus(chatID int64) (bool, string) {
    var user models.TelegramUser
    if err := database.DB.Where("chat_id = ?", chatID).First(&user).Error; err != nil {
        return false, "User not found"
    }

    if user.ExpiredIn == nil {
        return false, "No active license"
    }

    expiredTime, err := time.Parse("2006-01-02 15:04:05", *user.ExpiredIn)
    if err != nil {
        return false, "Invalid expiry date"
    }

    if time.Now().After(expiredTime) {
        return false, "License expired"
    }

    return true, "License active"
}
```

---

## Project Structure

```
trader-go/
├── main.go
├── go.mod
├── go.sum
├── .env
├── config/
│   └── config.go          # Viper configuration
├── models/
│   ├── telegram_user.go
│   ├── subscription.go
│   └── user.go
├── database/
│   └── database.go        # GORM connection
├── controllers/
│   ├── telegram_controller.go
│   ├── binance_controller.go
│   ├── bybit_controller.go
│   ├── license_controller.go
│   └── money_management_controller.go
├── services/
│   ├── binance_service.go
│   ├── telegram_service.go
│   └── bybit_service.go
├── middleware/
│   ├── auth.go
│   └── api_secret.go
├── routes/
│   └── routes.go
├── helpers/
│   ├── license.go
│   └── formatter.go
├── utils/
│   └── crypto.go
└── tests/
    └── ...
```

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
- [ ] Set up Go project structure
- [ ] Initialize Go modules and dependencies
- [ ] Create GORM models
- [ ] Set up database connection
- [ ] Configure Viper for environment variables
- [ ] Set up logging with Zap

### Phase 2: Core Services (Week 3-4)
- [ ] Implement BinanceService
  - [ ] Wallet balance
  - [ ] Order placement
  - [ ] Position management
  - [ ] Leverage setting
- [ ] Implement TelegramService
  - [ ] Webhook handling
  - [ ] Message parsing
  - [ ] Button callbacks
  - [ ] State management

### Phase 3: Controllers & Routes (Week 5-6)
- [ ] Implement all API controllers
  - [ ] Binance controller
  - [ ] Bybit controller (proxy)
  - [ ] License controller
  - [ ] Money management controller
- [ ] Set up Gin routes
- [ ] Add middleware (API secret, CORS, logging)

### Phase 4: Business Logic (Week 7-8)
- [ ] License validation integration
- [ ] Money management calculations
- [ ] Risk management logic
- [ ] Helper functions
- [ ] State machine for Telegram bot

### Phase 5: Testing (Week 9)
- [ ] Unit tests for services
- [ ] Integration tests for controllers
- [ ] End-to-end testing
- [ ] Load testing

### Phase 6: Deployment (Week 10)
- [ ] Dockerize application
- [ ] Set up CI/CD
- [ ] Production configuration
- [ ] Database migration scripts
- [ ] Monitoring and logging setup

---

## Code Examples

### Main Application Entry Point

```go
// main.go
package main

import (
    "log"

    "github.com/gin-gonic/gin"
    "github.com/spf13/viper"
    "go.uber.org/zap"

    "trader/config"
    "trader/database"
    "trader/routes"
)

func main() {
    // Load configuration
    config.LoadConfig()

    // Initialize logger
    logger, _ := zap.NewProduction()
    defer logger.Sync()

    // Connect to database
    database.Connect()

    // Setup Gin router
    r := gin.Default()

    // Setup routes
    routes.SetupRoutes(r)

    // Start server
    port := viper.GetString("APP_PORT")
    if port == "" {
        port = "8080"
    }

    log.Printf("Starting server on port %s", port)
    if err := r.Run(":" + port); err != nil {
        log.Fatal("Failed to start server:", err)
    }
}
```

### Config Package

```go
// config/config.go
package config

import (
    "log"

    "github.com/spf13/viper"
)

func LoadConfig() {
    viper.SetConfigFile(".env")
    viper.AutomaticEnv()

    if err := viper.ReadInConfig(); err != nil {
        log.Printf("Error reading config file: %s", err)
    }

    // Set defaults
    viper.SetDefault("APP_PORT", "8080")
    viper.SetDefault("DB_PORT", "3306")
    viper.SetDefault("DB_HOST", "127.0.0.1")
}
```

### Controller Example

```go
// controllers/binance_controller.go
package controllers

import (
    "net/http"

    "github.com/gin-gonic/gin"
    "trader/database"
    "trader/models"
    "trader/services"
)

var binanceService = services.NewBinanceService()

func BinanceWalletBalance(c *gin.Context) {
    var req struct {
        UserID string `json:"user_id" binding:"required"`
    }

    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"status": false, "msg": "Invalid request"})
        return
    }

    // Get user API keys
    var user models.TelegramUser
    if err := database.DB.Where("chat_id = ?", req.UserID).First(&user).Error; err != nil {
        c.JSON(http.StatusNotFound, gin.H{
            "status": false,
            "msg":    "User not found",
            "hint":   "API",
        })
        return
    }

    if user.BinanceAPIKey == nil || user.BinanceAPISecret == nil {
        c.JSON(http.StatusBadRequest, gin.H{
            "status": false,
            "msg":    "Binance API keys not configured",
            "hint":   "API",
        })
        return
    }

    // Get balance
    result, err := binanceService.GetWalletBalance(*user.BinanceAPIKey, *user.BinanceAPISecret)
    if err != nil {
        c.JSON(http.StatusInternalServerError, gin.H{
            "status": false,
            "msg":    err.Error(),
            "hint":   "API_CONNECTION",
        })
        return
    }

    c.JSON(http.StatusOK, result)
}

func BinancePlaceOrder(c *gin.Context) {
    var req struct {
        UserID      string  `json:"user_id" binding:"required"`
        Symbol      string  `json:"symbol" binding:"required"`
        Qty         float64 `json:"qty" binding:"required"`
        EntryPrice  float64 `json:"entryPrice" binding:"required"`
        StopLoss    float64 `json:"stopLoss"`
        TakeProfit  float64 `json:"takeProfit"`
        Leverage    int     `json:"leverage" binding:"required"`
        Type        string  `json:"type" binding:"required"` // BUY or SELL
    }

    if err := c.ShouldBindJSON(&req); err != nil {
        c.JSON(http.StatusBadRequest, gin.H{"status": false, "msg": "Invalid request"})
        return
    }

    // Get user
    var user models.TelegramUser
    if err := database.DB.Where("chat_id = ?", req.UserID).First(&user).Error; err != nil {
        c.JSON(http.StatusNotFound, gin.H{"status": false, "msg": "User not found"})
        return
    }

    if user.BinanceAPIKey == nil || user.BinanceAPISecret == nil {
        c.JSON(http.StatusBadRequest, gin.H{
            "status": false,
            "msg":    "Binance API keys not configured",
            "hint":   "API",
        })
        return
    }

    // Set leverage
    binanceService.SetLeverage(req.Symbol, req.Leverage, *user.BinanceAPIKey, *user.BinanceAPISecret)

    // Place order
    result, err := binanceService.PlaceLimitOrder(
        req.Symbol,
        req.Type,
        req.Qty,
        req.EntryPrice,
        *user.BinanceAPIKey,
        *user.BinanceAPISecret,
    )

    if err != nil {
        c.JSON(http.StatusInternalServerError, gin.H{
            "status": false,
            "msg":    err.Error(),
        })
        return
    }

    c.JSON(http.StatusOK, result)
}
```

### Middleware Example

```go
// middleware/api_secret.go
package middleware

import (
    "net/http"

    "github.com/gin-gonic/gin"
    "github.com/spf13/viper"
)

func CheckAPISecret() gin.HandlerFunc {
    return func(c *gin.Context) {
        apiSecret := c.GetHeader("API-SECRET")
        expectedSecret := viper.GetString("API_SECRET")

        if apiSecret != expectedSecret {
            c.JSON(http.StatusUnauthorized, gin.H{
                "status": false,
                "msg":    "Unauthorized: Invalid API secret",
            })
            c.Abort()
            return
        }

        c.Next()
    }
}
```

---

## Testing Strategy

### Unit Tests Example

```go
// services/binance_service_test.go
package services

import (
    "testing"

    "github.com/stretchr/testify/assert"
)

func TestGenerateSignature(t *testing.T) {
    service := NewBinanceService()

    queryString := "symbol=BTCUSDT&side=BUY&type=LIMIT&timeInForce=GTC&quantity=1&price=50000"
    apiSecret := "test_secret"

    signature := service.GenerateSignature(queryString, apiSecret)

    assert.NotEmpty(t, signature)
    assert.Equal(t, 64, len(signature)) // SHA256 hex string length
}

func TestGetWalletBalance(t *testing.T) {
    // Mock test - requires test API keys
    service := NewBinanceService()

    apiKey := "test_key"
    apiSecret := "test_secret"

    result, err := service.GetWalletBalance(apiKey, apiSecret)

    // In real tests, you'd mock the HTTP client
    assert.NotNil(t, result)
    assert.Error(t, err) // Will error with test credentials
}
```

### Integration Tests

```go
// tests/integration/api_test.go
package integration

import (
    "bytes"
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"

    "github.com/gin-gonic/gin"
    "github.com/stretchr/testify/assert"

    "trader/routes"
)

func setupRouter() *gin.Engine {
    r := gin.Default()
    routes.SetupRoutes(r)
    return r
}

func TestBinanceWalletBalanceAPI(t *testing.T) {
    router := setupRouter()

    payload := map[string]interface{}{
        "user_id": "123456789",
    }
    jsonPayload, _ := json.Marshal(payload)

    w := httptest.NewRecorder()
    req, _ := http.NewRequest("POST", "/api/binance/wallet-balance", bytes.NewBuffer(jsonPayload))
    req.Header.Set("Content-Type", "application/json")

    router.ServeHTTP(w, req)

    assert.Equal(t, http.StatusOK, w.Code)

    var response map[string]interface{}
    json.Unmarshal(w.Body.Bytes(), &response)

    assert.Contains(t, response, "status")
}
```

---

## Deployment Considerations

### Dockerfile

```dockerfile
# Multi-stage build
FROM golang:1.21-alpine AS builder

WORKDIR /app

# Copy go mod files
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o main .

# Final stage
FROM alpine:latest

RUN apk --no-cache add ca-certificates

WORKDIR /root/

# Copy binary from builder
COPY --from=builder /app/main .
COPY --from=builder /app/.env .

EXPOSE 8080

CMD ["./main"]
```

### docker-compose.yml

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
      - DB_DATABASE=trader
      - DB_USERNAME=root
      - DB_PASSWORD=secret
    depends_on:
      - db
    restart: unless-stopped

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: trader
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    restart: unless-stopped

volumes:
  mysql_data:
```

### systemd Service (Linux)

```ini
# /etc/systemd/system/trader.service
[Unit]
Description=Trader Go Application
After=network.target

[Service]
Type=simple
User=trader
WorkingDirectory=/opt/trader
ExecStart=/opt/trader/main
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

### Nginx Reverse Proxy

```nginx
server {
    listen 80;
    server_name trader.signalvision.ai;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Environment Variables (.env)

```env
APP_NAME=TraderGo
APP_ENV=production
APP_PORT=8080
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trader
DB_USERNAME=root
DB_PASSWORD=your_password

TELEGRAM_BOT_TOKEN=your_bot_token

SIGNAL_MANAGEMENT_END_POINT=https://your-endpoint.com
API_SECRET=your_api_secret

BINANCE_BASE_URL=https://fapi.binance.com
BYBIT_BASE_URL=https://api.bybit.com

SIGNALSHOT_PRODUCT_ID=your_product_id
SIGNALMANAGER_PRODUCT_ID=your_manager_id
```

---

## Performance Optimizations

### 1. Database Connection Pooling

```go
// database/database.go
sqlDB, err := DB.DB()
sqlDB.SetMaxIdleConns(10)
sqlDB.SetMaxOpenConns(100)
sqlDB.SetConnMaxLifetime(time.Hour)
```

### 2. Caching with Redis

```go
// services/cache_service.go
package services

import (
    "context"
    "time"

    "github.com/go-redis/redis/v8"
)

var ctx = context.Background()
var rdb *redis.Client

func InitRedis() {
    rdb = redis.NewClient(&redis.Options{
        Addr:     "localhost:6379",
        Password: "",
        DB:       0,
    })
}

func CacheSet(key string, value interface{}, expiration time.Duration) error {
    return rdb.Set(ctx, key, value, expiration).Err()
}

func CacheGet(key string) (string, error) {
    return rdb.Get(ctx, key).Result()
}
```

### 3. Goroutines for Concurrent Operations

```go
// Example: Process multiple orders concurrently
func ProcessOrders(orders []Order) {
    var wg sync.WaitGroup

    for _, order := range orders {
        wg.Add(1)
        go func(o Order) {
            defer wg.Done()
            processOrder(o)
        }(order)
    }

    wg.Wait()
}
```

---

## Migration Checklist

- [ ] Set up Go development environment
- [ ] Initialize project with go modules
- [ ] Install all dependencies
- [ ] Create database connection
- [ ] Migrate all models
- [ ] Implement BinanceService
- [ ] Implement TelegramService
- [ ] Create all controllers
- [ ] Set up routing
- [ ] Add middleware
- [ ] Implement helper functions
- [ ] Add comprehensive logging
- [ ] Write unit tests
- [ ] Write integration tests
- [ ] Set up Docker
- [ ] Configure production environment
- [ ] Set up monitoring (Prometheus/Grafana)
- [ ] Configure backup strategy
- [ ] Document API endpoints
- [ ] Create deployment scripts
- [ ] Perform load testing
- [ ] Security audit
- [ ] Final QA testing

---

## Additional Resources

### Go Learning Resources
- [Official Go Documentation](https://go.dev/doc/)
- [Effective Go](https://go.dev/doc/effective_go)
- [Go by Example](https://gobyexample.com/)

### Framework Documentation
- [Gin Web Framework](https://gin-gonic.com/docs/)
- [GORM Documentation](https://gorm.io/docs/)
- [Viper Configuration](https://github.com/spf13/viper)

### Binance API
- [Binance Futures API Documentation](https://binance-docs.github.io/apidocs/futures/en/)

### Telegram Bot
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [go-telegram-bot-api](https://github.com/go-telegram-bot-api/telegram-bot-api)

---

## Conclusion

Converting this Laravel application to Go will provide:
- **Better Performance**: 10-100x faster execution
- **Lower Resource Usage**: Smaller memory footprint
- **Easier Deployment**: Single binary deployment
- **Built-in Concurrency**: Native goroutines for concurrent operations
- **Type Safety**: Compile-time error checking
- **Better Scaling**: Handle more concurrent users

The conversion process is straightforward with the right planning and architecture. Follow the roadmap, test thoroughly, and deploy incrementally for best results.
