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
	bybitWSURL = "wss://stream.bybit.com/v5/public/linear"
)

// BybitClient manages Bybit WebSocket connections
type BybitClient struct {
	store             *store.TradeStore
	notificationQueue chan types.Notification
	ws                *websocket.Conn
	mu                sync.Mutex
	activeTopics      map[string]bool
	syncTimer         *time.Timer
	shutdown          chan struct{}
	wg                sync.WaitGroup
	reconnecting      bool
	lastProcessedTime map[string]time.Time // Throttle processing per symbol
}

// BybitTickerMessage represents Bybit ticker update
type BybitTickerMessage struct {
	Topic string `json:"topic"`
	Type  string `json:"type"`
	Data  struct {
		Symbol     string `json:"symbol"`
		Ask1Price  string `json:"ask1Price"`
		LastPrice  string `json:"lastPrice"`
		MarkPrice  string `json:"markPrice"`
		IndexPrice string `json:"indexPrice"`
	} `json:"data"`
}

// BybitSubscribeMessage represents subscription message
type BybitSubscribeMessage struct {
	Op   string   `json:"op"`
	Args []string `json:"args"`
}

// BybitResponse represents Bybit response
type BybitResponse struct {
	Success bool   `json:"success"`
	RetMsg  string `json:"ret_msg"`
	Op      string `json:"op"`
}

// NewBybitClient creates a new Bybit WebSocket client
func NewBybitClient(store *store.TradeStore, notifQueue chan types.Notification) *BybitClient {
	return &BybitClient{
		store:             store,
		notificationQueue: notifQueue,
		activeTopics:      make(map[string]bool),
		shutdown:          make(chan struct{}),
		lastProcessedTime: make(map[string]time.Time),
	}
}

// Start starts the Bybit WebSocket client
func (c *BybitClient) Start() {
	c.wg.Add(1)
	go c.connect()
}

// Stop stops the Bybit WebSocket client
func (c *BybitClient) Stop() {
	close(c.shutdown)
	c.mu.Lock()
	if c.ws != nil {
		c.ws.Close()
	}
	c.mu.Unlock()
	c.wg.Wait()
}

func (c *BybitClient) connect() {
	defer c.wg.Done()

	for {
		select {
		case <-c.shutdown:
			return
		default:
		}

		logger.Log.Info("Connecting to Bybit v5 WebSocket...")
		dialer := websocket.Dialer{
			HandshakeTimeout: 10 * time.Second,
		}

		ws, _, err := dialer.Dial(bybitWSURL, nil)
		if err != nil {
			logger.Log.Errorf("Bybit connection failed: %v. Retrying in 5s", err)
			time.Sleep(5 * time.Second)
			continue
		}

		c.mu.Lock()
		c.ws = ws
		c.mu.Unlock()

		logger.Log.Info("Connected to Bybit v5 WebSocket")

		// Resubscribe
		c.resubscribe()

		// Start ping goroutine
		pingDone := make(chan struct{})
		go c.pingLoop(ws, pingDone)

		// Read messages
		c.readMessages(ws)

		// Stop ping
		close(pingDone)

		// Connection closed
		c.mu.Lock()
		c.ws = nil
		c.activeTopics = make(map[string]bool)
		c.mu.Unlock()

		logger.Log.Warn("Bybit WebSocket closed. Reconnecting...")
		time.Sleep(2 * time.Second)
	}
}

func (c *BybitClient) pingLoop(ws *websocket.Conn, done chan struct{}) {
	ticker := time.NewTicker(20 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-done:
			return
		case <-ticker.C:
			c.mu.Lock()
			if c.ws != nil {
				ping := map[string]interface{}{"op": "ping"}
				data, _ := json.Marshal(ping)
				c.ws.WriteMessage(websocket.TextMessage, data)
			}
			c.mu.Unlock()
		}
	}
}

func (c *BybitClient) readMessages(ws *websocket.Conn) {
	for {
		_, message, err := ws.ReadMessage()
		if err != nil {
			logger.Log.Errorf("Bybit read error: %v", err)
			return
		}

		c.handleMessage(message)
	}
}

func (c *BybitClient) handleMessage(raw []byte) {
	// Try parse as response
	var resp BybitResponse
	if err := json.Unmarshal(raw, &resp); err == nil && resp.Op != "" {
		if resp.Op == "pong" {
			return
		}
		if resp.Success {
			return // Subscription ack
		}
	}

	// Parse as ticker message
	var msg BybitTickerMessage
	if err := json.Unmarshal(raw, &msg); err != nil {
		return
	}

	if !strings.HasPrefix(msg.Topic, "tickers.") {
		return
	}

	symbol := strings.ToUpper(strings.TrimPrefix(msg.Topic, "tickers."))
	key := store.KeyFor(types.MarketBybit, symbol)

	// Throttle: skip if processed this symbol recently (within 5 seconds)
	c.mu.Lock()
	lastTime, exists := c.lastProcessedTime[key]
	if exists && time.Since(lastTime) < messageThrottle {
		c.mu.Unlock()
		return
	}
	// Update timestamp immediately to throttle future messages
	c.lastProcessedTime[key] = time.Now()
	c.mu.Unlock()

	// Get price (prefer ask1Price, fallback to lastPrice)
	rawPrice := msg.Data.Ask1Price
	if rawPrice == "" {
		rawPrice = msg.Data.LastPrice
	}
	if rawPrice == "" {
		rawPrice = msg.Data.MarkPrice
	}
	if rawPrice == "" {
		rawPrice = msg.Data.IndexPrice
	}
	if rawPrice == "" {
		return
	}

	// Skip if unchanged
	if !c.store.CheckRawPriceChanged(key, rawPrice) {
		return
	}

	price, err := strconv.ParseFloat(rawPrice, 64)
	if err != nil {
		return
	}

	c.store.SetPrice(symbol, price)
	fmt.Printf("{\"key\":\"%s\",\"price\":%f}\n", key, price)

	// Get trades
	trades := c.store.GetTradesByKey(key)
	if len(trades) == 0 {
		return
	}

	// Check significance
	prevProcessed, exists := c.store.GetLastProcessedPrice(key)
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

	c.store.SetLastProcessedPrice(key, price)

	// Process trades
	result := processor.ProcessTrades(key, price, trades, types.MarketBybit)

	// Handle notifications
	for _, n := range result.Notifications {
		c.notificationQueue <- types.Notification{
			TradeID: n.TradeID,
			Type:    n.Type,
			Price:   fmt.Sprintf("%f", n.Price),
			Data:    n.Data,
		}
	}

	// Remove trades
	if len(result.RemoveTradeIDs) > 0 {
		c.store.RemoveTrades(key, result.RemoveTradeIDs)
		for _, id := range result.RemoveTradeIDs {
			logger.Log.Infof("Trade ID %d removed due to SL hit", id)
		}
		go c.ScheduleSync()
	}

	// Apply patches
	if len(result.Patches) > 0 {
		c.store.ApplyPatches(key, result.Patches)
	}
}

// ScheduleSync schedules a topic sync
func (c *BybitClient) ScheduleSync() {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.syncTimer != nil {
		return
	}

	c.syncTimer = time.AfterFunc(syncDebounceMS*time.Millisecond, func() {
		c.syncTopics()
	})
}

func (c *BybitClient) syncTopics() {
	c.mu.Lock()
	c.syncTimer = nil
	c.mu.Unlock()

	desired := c.desiredTopics()
	toSubscribe := []string{}
	toUnsubscribe := []string{}

	c.mu.Lock()
	for topic := range desired {
		if !c.activeTopics[topic] {
			toSubscribe = append(toSubscribe, topic)
		}
	}
	for topic := range c.activeTopics {
		if !desired[topic] {
			toUnsubscribe = append(toUnsubscribe, topic)
		}
	}
	c.mu.Unlock()

	if len(toSubscribe) > 0 {
		c.subscribe(toSubscribe)
		c.mu.Lock()
		for _, topic := range toSubscribe {
			c.activeTopics[topic] = true
		}
		c.mu.Unlock()
		logger.Log.Infof("Bybit SUBSCRIBE -> %s", strings.Join(toSubscribe, ", "))
	}

	if len(toUnsubscribe) > 0 {
		c.unsubscribe(toUnsubscribe)
		c.mu.Lock()
		for _, topic := range toUnsubscribe {
			delete(c.activeTopics, topic)
		}
		c.mu.Unlock()
		logger.Log.Infof("Bybit UNSUBSCRIBE -> %s", strings.Join(toUnsubscribe, ", "))
	}
}

func (c *BybitClient) desiredTopics() map[string]bool {
	coins := c.store.GetCoinsByMarket(types.MarketBybit)
	topics := make(map[string]bool)
	for _, coin := range coins {
		topic := fmt.Sprintf("tickers.%s", strings.ToUpper(coin))
		topics[topic] = true
	}
	return topics
}

func (c *BybitClient) subscribe(topics []string) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.ws == nil {
		return
	}

	msg := BybitSubscribeMessage{
		Op:   "subscribe",
		Args: topics,
	}

	data, _ := json.Marshal(msg)
	c.ws.WriteMessage(websocket.TextMessage, data)
}

func (c *BybitClient) unsubscribe(topics []string) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.ws == nil {
		return
	}

	msg := BybitSubscribeMessage{
		Op:   "unsubscribe",
		Args: topics,
	}

	data, _ := json.Marshal(msg)
	c.ws.WriteMessage(websocket.TextMessage, data)
}

func (c *BybitClient) resubscribe() {
	c.mu.Lock()
	c.activeTopics = make(map[string]bool)
	c.mu.Unlock()

	desired := c.desiredTopics()
	topics := []string{}
	for topic := range desired {
		topics = append(topics, topic)
	}

	if len(topics) > 0 {
		c.subscribe(topics)
		c.mu.Lock()
		for _, topic := range topics {
			c.activeTopics[topic] = true
		}
		c.mu.Unlock()
		logger.Log.Infof("Bybit (Re)SUBSCRIBE on open -> %s", strings.Join(topics, ", "))
	}
}
