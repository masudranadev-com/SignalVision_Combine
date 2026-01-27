package api

import (
	"fmt"
	"net/http"
	"signalnode/internal/logger"
	"signalnode/internal/store"
	"signalnode/internal/types"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
)

// Server manages the HTTP API server
type Server struct {
	router               *gin.Engine
	store                *store.TradeStore
	scheduleSyncBinance  func()
	scheduleSyncBybit    func()
}

// NewServer creates a new API server
func NewServer(tradeStore *store.TradeStore, scheduleSyncBinance, scheduleSyncBybit func()) *Server {
	gin.SetMode(gin.ReleaseMode)
	router := gin.New()
	router.Use(gin.Recovery())

	s := &Server{
		router:              router,
		store:               tradeStore,
		scheduleSyncBinance: scheduleSyncBinance,
		scheduleSyncBybit:   scheduleSyncBybit,
	}

	s.setupRoutes()
	return s
}

func (s *Server) setupRoutes() {
	// Enable CORS for frontend
	s.router.Use(func(c *gin.Context) {
		c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
		c.Writer.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		c.Writer.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		if c.Request.Method == "OPTIONS" {
			c.AbortWithStatus(204)
			return
		}
		c.Next()
	})

	// Serve static resources
	s.router.Static("/resources", "./resources")

	// Admin panel routes
	s.router.GET("/", func(c *gin.Context) {
		c.Redirect(302, "/admin/dashboard")
	})
	s.router.GET("/admin", func(c *gin.Context) {
		c.Redirect(302, "/admin/dashboard")
	})
	s.router.GET("/admin/login", func(c *gin.Context) {
		c.File("./resources/views/admin/pages/login.html")
	})
	s.router.GET("/admin/dashboard", func(c *gin.Context) {
		c.File("./resources/views/admin/pages/dashboard.html")
	})
	s.router.GET("/admin/trades", func(c *gin.Context) {
		c.File("./resources/views/admin/pages/trades.html")
	})
	s.router.GET("/admin/settings", func(c *gin.Context) {
		c.File("./resources/views/admin/pages/settings.html")
	})

	// API routes
	s.router.GET("/health", s.health)
	s.router.GET("/api/prices", s.prices)
	s.router.GET("/api/trades", s.trades)
	s.router.GET("/api/stats", s.stats)
	s.router.POST("/api/config-trade", s.configTrade)
	s.router.POST("/api/login", s.login)
}

// Start starts the HTTP server
func (s *Server) Start(port int) error {
	addr := fmt.Sprintf(":%d", port)
	logger.Log.Infof("HTTP Server starting on port %d", port)
	return s.router.Run(addr)
}

func (s *Server) health(c *gin.Context) {
	c.Header("Cache-Control", "no-store")
	c.String(http.StatusOK, "ok")
}

func (s *Server) prices(c *gin.Context) {
	prices := s.store.GetPrices()
	c.JSON(http.StatusOK, prices)
}

func (s *Server) trades(c *gin.Context) {
	trades := s.store.GetAllTrades()
	c.JSON(http.StatusOK, trades)
}

func (s *Server) stats(c *gin.Context) {
	trades := s.store.GetAllTrades()
	prices := s.store.GetPrices()

	// Calculate statistics
	binanceCount := 0
	bybitCount := 0
	waitingCount := 0
	runningCount := 0

	for _, trade := range trades {
		if trade.Market == "binance" {
			binanceCount++
		} else if trade.Market == "bybit" {
			bybitCount++
		}

		if trade.Status == "waiting" {
			waitingCount++
		} else if trade.Status == "running" {
			runningCount++
		}
	}

	c.JSON(http.StatusOK, gin.H{
		"total_trades":    len(trades),
		"binance_trades":  binanceCount,
		"bybit_trades":    bybitCount,
		"waiting_trades":  waitingCount,
		"running_trades":  runningCount,
		"tracked_prices":  len(prices),
	})
}

func (s *Server) login(c *gin.Context) {
	var req struct {
		Username string `json:"username"`
		Password string `json:"password"`
	}

	if err := c.BindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Invalid request"})
		return
	}

	// Simple authentication (change credentials in production)
	if req.Username == "admin" && req.Password == "admin123" {
		c.JSON(http.StatusOK, gin.H{
			"success": true,
			"token":   "demo-token-12345",
			"user": gin.H{
				"username": req.Username,
				"role":     "admin",
			},
		})
	} else {
		c.JSON(http.StatusUnauthorized, gin.H{"success": false, "error": "Invalid credentials"})
	}
}

func (s *Server) configTrade(c *gin.Context) {
	var req struct {
		Type  string           `json:"type"`
		Trade interface{}      `json:"trade,omitempty"` // Can be APITrade object or ID for delete
	}

	if err := c.BindJSON(&req); err != nil {
		logger.Log.Errorf("Invalid request body: %v", err)
		c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Invalid request"})
		return
	}

	if req.Type == "" {
		logger.Log.Error("Missing type in request")
		c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Missing type"})
		return
	}

	switch req.Type {
	case "add", "update":
		if req.Trade == nil {
			logger.Log.Error("Missing trade payload for add/update")
			c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Missing trade data"})
			return
		}

		// Parse trade object from interface{}
		tradeData, ok := req.Trade.(map[string]interface{})
		if !ok {
			logger.Log.Error("Invalid trade data format")
			c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Invalid trade data format"})
			return
		}

		apiTrade := s.parseTradeFromMap(tradeData)
		trade := s.convertAPITrade(apiTrade)
		s.store.AddOrUpdateTrade(trade)
		logger.Log.Infof("[TradeConfig] Trade %s processed: %d", req.Type, apiTrade.ID)

		// Schedule sync for both exchanges
		go s.scheduleSyncBinance()
		go s.scheduleSyncBybit()

		c.JSON(http.StatusOK, gin.H{"status": true})

	case "delete":
		if req.Trade == nil {
			logger.Log.Error("Missing trade ID for delete")
			c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Missing trade ID"})
			return
		}

		// Parse trade ID
		var tradeID int
		switch v := req.Trade.(type) {
		case float64:
			tradeID = int(v)
		case string:
			parsed, err := strconv.Atoi(v)
			if err != nil {
				logger.Log.Errorf("Invalid trade ID: %v", v)
				c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Invalid trade ID"})
				return
			}
			tradeID = parsed
		default:
			logger.Log.Errorf("Invalid trade ID type: %T", v)
			c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Invalid trade ID"})
			return
		}

		s.store.DeleteTrade(tradeID)
		logger.Log.Infof("[TradeConfig] Trade deleted: %d", tradeID)

		// Schedule sync
		go s.scheduleSyncBinance()
		go s.scheduleSyncBybit()

		c.JSON(http.StatusOK, gin.H{"status": true})

	default:
		logger.Log.Errorf("Unknown trade action: %s", req.Type)
		c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Unknown action"})
	}
}

// Helper function to safely extract float from interface{} (handles both float64 and string)
func getFloatFromInterface(data map[string]interface{}, key string) float64 {
	if val, ok := data[key]; ok && val != nil {
		switch v := val.(type) {
		case float64:
			return v
		case int:
			return float64(v)
		case string:
			var f float64
			fmt.Sscanf(v, "%f", &f)
			return f
		}
	}
	return 0
}

func (s *Server) parseTradeFromMap(data map[string]interface{}) *types.APITrade {
	apiTrade := &types.APITrade{}

	if id, ok := data["id"].(float64); ok {
		apiTrade.ID = int(id)
	} else if idInt, ok := data["id"].(int); ok {
		apiTrade.ID = idInt
	}
	if market, ok := data["market"].(string); ok {
		apiTrade.Market = market
	}
	if tpMode, ok := data["tp_mode"].(string); ok {
		apiTrade.TPMode = tpMode
	}
	if instruments, ok := data["instruments"].(string); ok {
		apiTrade.Instruments = instruments
	}

	apiTrade.EntryTarget = getFloatFromInterface(data, "entry_target")
	apiTrade.StopLossPercentage = getFloatFromInterface(data, "stop_loss_percentage")
	apiTrade.StopLossPrice = getFloatFromInterface(data, "stop_loss_price")
	apiTrade.StopLoss = getFloatFromInterface(data, "stop_loss")
	apiTrade.TakeProfit1 = getFloatFromInterface(data, "take_profit1")
	apiTrade.TakeProfit2 = getFloatFromInterface(data, "take_profit2")
	apiTrade.TakeProfit3 = getFloatFromInterface(data, "take_profit3")
	apiTrade.TakeProfit4 = getFloatFromInterface(data, "take_profit4")
	apiTrade.TakeProfit5 = getFloatFromInterface(data, "take_profit5")
	apiTrade.TakeProfit6 = getFloatFromInterface(data, "take_profit6")
	apiTrade.TakeProfit7 = getFloatFromInterface(data, "take_profit7")
	apiTrade.TakeProfit8 = getFloatFromInterface(data, "take_profit8")
	apiTrade.TakeProfit9 = getFloatFromInterface(data, "take_profit9")
	apiTrade.TakeProfit10 = getFloatFromInterface(data, "take_profit10")
	apiTrade.HeightPrice = getFloatFromInterface(data, "height_price")
	if status, ok := data["status"].(string); ok {
		apiTrade.Status = status
	}
	if lastAlert, ok := data["last_alert"].(string); ok {
		apiTrade.LastAlert = &lastAlert
	}

	return apiTrade
}

func (s *Server) convertAPITrade(apiTrade *types.APITrade) *types.Trade {
	return &types.Trade{
		Market:           strings.ToLower(apiTrade.Market),
		Mod:              strings.ToUpper(apiTrade.TPMode),
		Pair:             strings.ToUpper(apiTrade.Instruments),
		TradeID:          apiTrade.ID,
		Entry:            apiTrade.EntryTarget,
		SL:               apiTrade.StopLoss,
		SLPercentage:     apiTrade.StopLossPercentage,
		SLPrice:          apiTrade.StopLossPrice,
		TP1:              apiTrade.TakeProfit1,
		TP2:              apiTrade.TakeProfit2,
		TP3:              apiTrade.TakeProfit3,
		TP4:              apiTrade.TakeProfit4,
		TP5:              apiTrade.TakeProfit5,
		TP6:              apiTrade.TakeProfit6,
		TP7:              apiTrade.TakeProfit7,
		TP8:              apiTrade.TakeProfit8,
		TP9:              apiTrade.TakeProfit9,
		TP10:             apiTrade.TakeProfit10,
		HeightPrice:      apiTrade.HeightPrice,
		Status:           apiTrade.Status,
		LastNotification: apiTrade.LastAlert,
	}
}
