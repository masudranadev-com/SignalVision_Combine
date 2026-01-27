package exchange

import (
	"encoding/json"
	"fmt"
	"signalnode/internal/logger"
	"signalnode/internal/processor"
	"signalnode/internal/store"
	"signalnode/internal/types"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/gorilla/websocket"
)

const (
	binanceWSURL      = "wss://fstream.binance.com/stream"
	priceChangeAbs    = 0.0      // Absolute price change threshold (disabled)
	priceChangePct    = 0.0002   // 0.02% (2 basis points)
	syncDebounceMS    = 100      // Debounce for stream sync
	maxReconnectDelay = 30000    // Max reconnect delay in ms
	initialReconnect  = 1000     // Initial reconnect delay in ms
	messageThrottle   = 5 * time.Second // Minimum time between processing same symbol
)

// BinanceClient manages Binance WebSocket connections
type BinanceClient struct {
	store             *store.TradeStore
	notificationQueue chan types.Notification
	ws                *websocket.Conn
	mu                sync.Mutex
	activeStreams     map[string]bool
	reconnectAttempts int
	requestID         int
	pendingMsgs       []string
	syncTimer         *time.Timer
	shutdown          chan struct{}
	wg                sync.WaitGroup
	lastProcessedTime map[string]time.Time // Throttle processing per symbol
}

// BinanceAggTradeMessage represents the Binance aggTrade message
type BinanceAggTradeMessage struct {
	Stream string `json:"stream"`
	Data   struct {
		Symbol string `json:"s"` // BTCUSDT
		Price  string `json:"p"` // Price
	} `json:"data"`
}

// BinanceResponse represents subscription response
type BinanceResponse struct {
	Result interface{} `json:"result"`
	ID     int         `json:"id"`
}

// NewBinanceClient creates a new Binance WebSocket client
func NewBinanceClient(store *store.TradeStore, notifQueue chan types.Notification) *BinanceClient {
	return &BinanceClient{
		store:             store,
		notificationQueue: notifQueue,
		activeStreams:     make(map[string]bool),
		shutdown:          make(chan struct{}),
		lastProcessedTime: make(map[string]time.Time),
	}
}

// Start starts the Binance WebSocket client
func (b *BinanceClient) Start() {
	b.wg.Add(1)
	go b.connect()
}

// Stop stops the Binance WebSocket client
func (b *BinanceClient) Stop() {
	close(b.shutdown)
	b.mu.Lock()
	if b.ws != nil {
		b.ws.Close()
	}
	b.mu.Unlock()
	b.wg.Wait()
}

func (b *BinanceClient) connect() {
	defer b.wg.Done()

	for {
		select {
		case <-b.shutdown:
			return
		default:
		}

		logger.Log.Info("Connecting to Binance Futures WebSocket...")
		dialer := websocket.Dialer{
			HandshakeTimeout: 10 * time.Second,
		}

		ws, _, err := dialer.Dial(binanceWSURL, nil)
		if err != nil {
			delay := b.calculateBackoff()
			logger.Log.Errorf("Binance connection failed: %v. Retrying in %dms", err, delay)
			time.Sleep(time.Duration(delay) * time.Millisecond)
			b.reconnectAttempts++
			continue
		}

		b.mu.Lock()
		b.ws = ws
		b.reconnectAttempts = 0
		b.mu.Unlock()

		logger.Log.Info("Connected to Binance Futures WebSocket")

		// Send pending messages
		b.mu.Lock()
		for len(b.pendingMsgs) > 0 {
			msg := b.pendingMsgs[0]
			b.pendingMsgs = b.pendingMsgs[1:]
			ws.WriteMessage(websocket.TextMessage, []byte(msg))
		}
		b.mu.Unlock()

		// Resubscribe to streams
		b.resubscribe()

		// Read messages
		b.readMessages(ws)

		// Connection closed, cleanup
		b.mu.Lock()
		b.ws = nil
		b.activeStreams = make(map[string]bool)
		b.mu.Unlock()

		logger.Log.Warn("Binance WebSocket connection closed")

		// Check if we should reconnect
		if b.reconnectAttempts >= 5 {
			logger.Log.Error("Max reconnect attempts reached for Binance")
			// Send error notification
			b.notificationQueue <- types.Notification{
				TradeID: 0,
				Type:    "ERROR",
				Price:   "PRICE",
				Data:    map[string]interface{}{"msg": "Max reconnect attempts reached for Binance."},
			}
			return
		}

		delay := b.calculateBackoff()
		logger.Log.Infof("Reconnecting to Binance in %dms (Attempt %d/5)", delay, b.reconnectAttempts+1)
		time.Sleep(time.Duration(delay) * time.Millisecond)
		b.reconnectAttempts++
	}
}

func (b *BinanceClient) calculateBackoff() int {
	delay := initialReconnect * (1 << b.reconnectAttempts)
	if delay > maxReconnectDelay {
		delay = maxReconnectDelay
	}
	return delay
}

func (b *BinanceClient) readMessages(ws *websocket.Conn) {
	for {
		_, message, err := ws.ReadMessage()
		if err != nil {
			logger.Log.Errorf("Binance read error: %v", err)
			return
		}

		b.handleMessage(message)
	}
}

func (b *BinanceClient) handleMessage(raw []byte) {
	// Try to parse as response (ack)
	var resp BinanceResponse
	if err := json.Unmarshal(raw, &resp); err == nil && resp.Result != nil {
		return // Subscription ack
	}

	// Parse as aggTrade message
	var msg BinanceAggTradeMessage
	if err := json.Unmarshal(raw, &msg); err != nil {
		return
	}

	if msg.Data.Symbol == "" || msg.Data.Price == "" {
		return
	}

	symbol := strings.ToUpper(msg.Data.Symbol)
	rawPrice := msg.Data.Price
	key := store.KeyFor(types.MarketBinance, symbol)

	// Throttle: skip if processed this symbol recently (within 5 seconds)
	b.mu.Lock()
	lastTime, exists := b.lastProcessedTime[key]
	if exists && time.Since(lastTime) < messageThrottle {
		b.mu.Unlock()
		return
	}
	// Update timestamp immediately to throttle future messages
	b.lastProcessedTime[key] = time.Now()
	b.mu.Unlock()

	// Skip if raw price unchanged
	if !b.store.CheckRawPriceChanged(key, rawPrice) {
		return
	}

	price, err := strconv.ParseFloat(rawPrice, 64)
	if err != nil {
		return
	}

	b.store.SetPrice(symbol, price)
	fmt.Printf("{\"key\":\"%s\",\"price\":%f}\n", key, price)

	// Get trades for this key
	trades := b.store.GetTradesByKey(key)
	if len(trades) == 0 {
		return
	}

	// Check if significant price change
	prevProcessed, exists := b.store.GetLastProcessedPrice(key)
	significant := false

	if !exists {
		significant = true
	} else {
		absDelta := abs(price - prevProcessed)
		relDelta := absDelta / prevProcessed
		if prevProcessed == 0 {
			relDelta = absDelta
		}

		absOk := priceChangeAbs > 0 && absDelta >= priceChangeAbs
		pctOk := priceChangePct > 0 && relDelta >= priceChangePct
		significant = absOk || pctOk

		if !significant && processor.HasCriticalCross(trades, prevProcessed, price) {
			significant = true
		}
	}

	if !significant {
		return
	}

	b.store.SetLastProcessedPrice(key, price)

	// Process trades
	result := processor.ProcessTrades(key, price, trades, types.MarketBinance)

	// Handle notifications
	for _, n := range result.Notifications {
		b.notificationQueue <- types.Notification{
			TradeID: n.TradeID,
			Type:    n.Type,
			Price:   fmt.Sprintf("%f", n.Price),
			Data:    n.Data,
		}
	}

	// Remove trades on SL
	if len(result.RemoveTradeIDs) > 0 {
		b.store.RemoveTrades(key, result.RemoveTradeIDs)
		for _, id := range result.RemoveTradeIDs {
			logger.Log.Infof("Trade ID %d removed due to SL hit", id)
		}
		go b.ScheduleSync()
	}

	// Apply patches
	if len(result.Patches) > 0 {
		b.store.ApplyPatches(key, result.Patches)
	}
}

func abs(x float64) float64 {
	if x < 0 {
		return -x
	}
	return x
}

// ScheduleSync schedules a stream sync
func (b *BinanceClient) ScheduleSync() {
	b.mu.Lock()
	defer b.mu.Unlock()

	if b.syncTimer != nil {
		return
	}

	b.syncTimer = time.AfterFunc(syncDebounceMS*time.Millisecond, func() {
		b.syncStreams()
	})
}

func (b *BinanceClient) syncStreams() {
	b.mu.Lock()
	b.syncTimer = nil
	b.mu.Unlock()

	desired := b.desiredStreams()
	toSubscribe := []string{}
	toUnsubscribe := []string{}

	b.mu.Lock()
	for s := range desired {
		if !b.activeStreams[s] {
			toSubscribe = append(toSubscribe, s)
		}
	}
	for s := range b.activeStreams {
		if !desired[s] {
			toUnsubscribe = append(toUnsubscribe, s)
		}
	}
	b.mu.Unlock()

	if len(toSubscribe) > 0 {
		b.subscribe(toSubscribe)
		b.mu.Lock()
		for _, s := range toSubscribe {
			b.activeStreams[s] = true
		}
		b.mu.Unlock()
		logger.Log.Infof("BINANCE SUBSCRIBE -> %s", strings.Join(toSubscribe, ", "))
	}

	if len(toUnsubscribe) > 0 {
		b.unsubscribe(toUnsubscribe)
		b.mu.Lock()
		for _, s := range toUnsubscribe {
			delete(b.activeStreams, s)
		}
		b.mu.Unlock()
		logger.Log.Infof("BINANCE UNSUBSCRIBE -> %s", strings.Join(toUnsubscribe, ", "))
	}
}

func (b *BinanceClient) desiredStreams() map[string]bool {
	coins := b.store.GetCoinsByMarket(types.MarketBinance)
	streams := make(map[string]bool)
	for _, coin := range coins {
		stream := fmt.Sprintf("%s@aggTrade", strings.ToLower(coin))
		streams[stream] = true
	}
	return streams
}

func (b *BinanceClient) subscribe(streams []string) {
	b.mu.Lock()
	defer b.mu.Unlock()

	b.requestID++
	msg := map[string]interface{}{
		"method": "SUBSCRIBE",
		"params": streams,
		"id":     b.requestID,
	}

	data, _ := json.Marshal(msg)
	b.send(string(data))
}

func (b *BinanceClient) unsubscribe(streams []string) {
	b.mu.Lock()
	defer b.mu.Unlock()

	b.requestID++
	msg := map[string]interface{}{
		"method": "UNSUBSCRIBE",
		"params": streams,
		"id":     b.requestID,
	}

	data, _ := json.Marshal(msg)
	b.send(string(data))
}

func (b *BinanceClient) send(msg string) {
	if b.ws != nil {
		b.ws.WriteMessage(websocket.TextMessage, []byte(msg))
	} else {
		b.pendingMsgs = append(b.pendingMsgs, msg)
	}
}

func (b *BinanceClient) resubscribe() {
	b.mu.Lock()
	b.activeStreams = make(map[string]bool)
	b.mu.Unlock()

	desired := b.desiredStreams()
	streams := []string{}
	for s := range desired {
		streams = append(streams, s)
	}

	if len(streams) > 0 {
		b.subscribe(streams)
		b.mu.Lock()
		for _, s := range streams {
			b.activeStreams[s] = true
		}
		b.mu.Unlock()
		logger.Log.Infof("BINANCE (Re)SUBSCRIBE on open -> %s", strings.Join(streams, ", "))
	}
}
