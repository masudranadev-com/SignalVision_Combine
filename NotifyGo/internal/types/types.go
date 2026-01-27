package types

import "sync"

// Market constants
const (
	MarketBinance = "binance"
	MarketBybit   = "bybit"
)

// Trade status constants
const (
	StatusWaiting = "waiting"
	StatusRunning = "running"
)

// Trade mode constants
const (
	ModeLong  = "LONG"
	ModeShort = "SHORT"
)

// Trade represents a single trade configuration
type Trade struct {
	sync.RWMutex
	Market           string   `json:"market"`
	Mod              string   `json:"mod"`
	Pair             string   `json:"pair"`
	TradeID          int      `json:"trade_id"`
	Entry            float64  `json:"entry"`
	SL               float64  `json:"sl"`
	SLPercentage     float64  `json:"sl_percentage"`
	SLPrice          float64  `json:"sl_price"`
	TP1              float64  `json:"tp1"`
	TP2              float64  `json:"tp2"`
	TP3              float64  `json:"tp3"`
	TP4              float64  `json:"tp4"`
	TP5              float64  `json:"tp5"`
	TP6              float64  `json:"tp6"`
	TP7              float64  `json:"tp7"`
	TP8              float64  `json:"tp8"`
	TP9              float64  `json:"tp9"`
	TP10             float64  `json:"tp10"`
	HeightPrice      float64  `json:"height_price"`
	Status           string   `json:"status"`
	LastNotification *string  `json:"last_notification"`
}

// PriceUpdate represents a price update from WebSocket
type PriceUpdate struct {
	Market string
	Symbol string
	Price  float64
	Key    string // market:PAIR
}

// Notification represents a notification to be sent
type Notification struct {
	TradeID int                    `json:"id"`
	Type    string                 `json:"type"`
	Price   string                 `json:"current_price"`
	Data    map[string]interface{} `json:"data"`
}

// APITradeRequest represents incoming trade API request
type APITradeRequest struct {
	Type  string      `json:"type"`
	Trade *APITrade   `json:"trade,omitempty"`
	ID    interface{} `json:"trade,omitempty"` // For delete operations
}

// APITrade represents trade data from API
type APITrade struct {
	ID                  int     `json:"id"`
	Market              string  `json:"market"`
	TPMode              string  `json:"tp_mode"`
	Instruments         string  `json:"instruments"`
	EntryTarget         float64 `json:"entry_target"`
	StopLossPercentage  float64 `json:"stop_loss_percentage"`
	StopLossPrice       float64 `json:"stop_loss_price"`
	StopLoss            float64 `json:"stop_loss"`
	TakeProfit1         float64 `json:"take_profit1"`
	TakeProfit2         float64 `json:"take_profit2"`
	TakeProfit3         float64 `json:"take_profit3"`
	TakeProfit4         float64 `json:"take_profit4"`
	TakeProfit5         float64 `json:"take_profit5"`
	TakeProfit6         float64 `json:"take_profit6"`
	TakeProfit7         float64 `json:"take_profit7"`
	TakeProfit8         float64 `json:"take_profit8"`
	TakeProfit9         float64 `json:"take_profit9"`
	TakeProfit10        float64 `json:"take_profit10"`
	HeightPrice         float64 `json:"height_price"`
	Status              string  `json:"status"`
	LastAlert           *string `json:"last_alert"`
}

// ProcessResult represents the result of trade processing
type ProcessResult struct {
	Symbol         string
	Notifications  []NotificationEvent
	RemoveTradeIDs []int
	Patches        []TradePatch
}

// NotificationEvent represents a notification event
type NotificationEvent struct {
	TradeID int                    `json:"trade_id"`
	Type    string                 `json:"type"`
	Price   float64                `json:"price"`
	Data    map[string]interface{} `json:"data"`
}

// TradePatch represents a patch to apply to a trade
type TradePatch struct {
	TradeID          int
	Status           string
	LastNotification *string
	HeightPrice      *float64
	SL               *float64
}
