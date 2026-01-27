package store

import (
	"fmt"
	"signalnode/internal/types"
	"strings"
	"sync"
)

// TradeStore manages all trades in memory with concurrent access
type TradeStore struct {
	mu              sync.RWMutex
	tradesByKey     map[string][]*types.Trade // "market:PAIR" -> []*Trade
	tradeIDToKey    map[int]string            // trade_id -> "market:PAIR"
	prices          map[string]float64        // symbol -> price
	lastRawPrice    map[string]string         // key -> raw price string
	lastProcessed   map[string]float64        // key -> last processed price
	updateChannels  map[string]chan types.PriceUpdate
	channelsMu      sync.RWMutex
}

// NewTradeStore creates a new trade store
func NewTradeStore() *TradeStore {
	return &TradeStore{
		tradesByKey:    make(map[string][]*types.Trade),
		tradeIDToKey:   make(map[int]string),
		prices:         make(map[string]float64),
		lastRawPrice:   make(map[string]string),
		lastProcessed:  make(map[string]float64),
		updateChannels: make(map[string]chan types.PriceUpdate),
	}
}

// KeyFor creates a composite key from market and pair
func KeyFor(market, pair string) string {
	return fmt.Sprintf("%s:%s", strings.ToLower(market), strings.ToUpper(pair))
}

// SplitKey splits a composite key into market and pair
func SplitKey(key string) (string, string) {
	parts := strings.SplitN(key, ":", 2)
	if len(parts) != 2 {
		return "", ""
	}
	return parts[0], parts[1]
}

// AddOrUpdateTrade adds or updates a trade
func (s *TradeStore) AddOrUpdateTrade(trade *types.Trade) {
	s.mu.Lock()
	defer s.mu.Unlock()

	key := KeyFor(trade.Market, trade.Pair)

	// Remove from old location if trade_id exists
	if oldKey, exists := s.tradeIDToKey[trade.TradeID]; exists {
		if oldList, ok := s.tradesByKey[oldKey]; ok {
			newList := make([]*types.Trade, 0, len(oldList))
			for _, t := range oldList {
				if t.TradeID != trade.TradeID {
					newList = append(newList, t)
				}
			}
			if len(newList) == 0 {
				delete(s.tradesByKey, oldKey)
			} else {
				s.tradesByKey[oldKey] = newList
			}
		}
		delete(s.tradeIDToKey, trade.TradeID)
	}

	// Add to new location
	list := s.tradesByKey[key]
	found := false
	for i, t := range list {
		if t.TradeID == trade.TradeID {
			list[i] = trade
			found = true
			break
		}
	}
	if !found {
		list = append(list, trade)
	}

	s.tradesByKey[key] = list
	s.tradeIDToKey[trade.TradeID] = key
}

// DeleteTrade removes a trade by ID
func (s *TradeStore) DeleteTrade(tradeID int) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	key, exists := s.tradeIDToKey[tradeID]
	if !exists {
		return false
	}

	if list, ok := s.tradesByKey[key]; ok {
		newList := make([]*types.Trade, 0, len(list))
		for _, t := range list {
			if t.TradeID != tradeID {
				newList = append(newList, t)
			}
		}
		if len(newList) == 0 {
			delete(s.tradesByKey, key)
			delete(s.lastRawPrice, key)
			delete(s.lastProcessed, key)
		} else {
			s.tradesByKey[key] = newList
		}
	}

	delete(s.tradeIDToKey, tradeID)
	return true
}

// GetTradesByKey returns all trades for a given key
func (s *TradeStore) GetTradesByKey(key string) []*types.Trade {
	s.mu.RLock()
	defer s.mu.RUnlock()

	trades := s.tradesByKey[key]
	// Return a copy to avoid concurrent modification
	result := make([]*types.Trade, len(trades))
	copy(result, trades)
	return result
}

// GetAllTrades returns all trades
func (s *TradeStore) GetAllTrades() []*types.Trade {
	s.mu.RLock()
	defer s.mu.RUnlock()

	var result []*types.Trade
	for _, trades := range s.tradesByKey {
		result = append(result, trades...)
	}
	return result
}

// GetCoinsByMarket returns unique symbols for a given market
func (s *TradeStore) GetCoinsByMarket(market string) []string {
	s.mu.RLock()
	defer s.mu.RUnlock()

	coins := make(map[string]bool)
	prefix := strings.ToLower(market) + ":"

	for key, trades := range s.tradesByKey {
		if len(trades) > 0 && strings.HasPrefix(key, prefix) {
			_, symbol := SplitKey(key)
			coins[strings.ToUpper(symbol)] = true
		}
	}

	result := make([]string, 0, len(coins))
	for coin := range coins {
		result = append(result, coin)
	}
	return result
}

// SetPrice sets the current price for a symbol
func (s *TradeStore) SetPrice(symbol string, price float64) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.prices[symbol] = price
}

// GetPrices returns all current prices
func (s *TradeStore) GetPrices() map[string]float64 {
	s.mu.RLock()
	defer s.mu.RUnlock()

	result := make(map[string]float64, len(s.prices))
	for k, v := range s.prices {
		result[k] = v
	}
	return result
}

// CheckRawPriceChanged checks if raw price string has changed
func (s *TradeStore) CheckRawPriceChanged(key, rawPrice string) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	last, exists := s.lastRawPrice[key]
	if exists && last == rawPrice {
		return false
	}
	s.lastRawPrice[key] = rawPrice
	return true
}

// GetLastProcessedPrice returns the last processed price for a key
func (s *TradeStore) GetLastProcessedPrice(key string) (float64, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	price, exists := s.lastProcessed[key]
	return price, exists
}

// SetLastProcessedPrice sets the last processed price for a key
func (s *TradeStore) SetLastProcessedPrice(key string, price float64) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.lastProcessed[key] = price
}

// ApplyPatches applies patches to trades
func (s *TradeStore) ApplyPatches(key string, patches []types.TradePatch) {
	s.mu.Lock()
	defer s.mu.Unlock()

	list := s.tradesByKey[key]
	if list == nil {
		return
	}

	// Create lookup map
	byID := make(map[int]*types.Trade)
	for _, t := range list {
		byID[t.TradeID] = t
	}

	// Apply patches
	for _, p := range patches {
		t, exists := byID[p.TradeID]
		if !exists {
			continue
		}

		t.Lock()
		if p.Status != "" {
			t.Status = p.Status
		}
		if p.LastNotification != nil {
			t.LastNotification = p.LastNotification
		}
		if p.HeightPrice != nil {
			t.HeightPrice = *p.HeightPrice
		}
		if p.SL != nil {
			t.SL = *p.SL
		}
		t.Unlock()
	}
}

// RemoveTrades removes multiple trades by ID
func (s *TradeStore) RemoveTrades(key string, tradeIDs []int) {
	s.mu.Lock()
	defer s.mu.Unlock()

	list := s.tradesByKey[key]
	if list == nil {
		return
	}

	removeMap := make(map[int]bool)
	for _, id := range tradeIDs {
		removeMap[id] = true
	}

	newList := make([]*types.Trade, 0, len(list))
	for _, t := range list {
		if !removeMap[t.TradeID] {
			newList = append(newList, t)
		} else {
			delete(s.tradeIDToKey, t.TradeID)
		}
	}

	if len(newList) == 0 {
		delete(s.tradesByKey, key)
		delete(s.lastRawPrice, key)
		delete(s.lastProcessed, key)
	} else {
		s.tradesByKey[key] = newList
	}
}

// GetUpdateChannel gets or creates a price update channel for a key
func (s *TradeStore) GetUpdateChannel(key string) chan types.PriceUpdate {
	s.channelsMu.Lock()
	defer s.channelsMu.Unlock()

	if ch, exists := s.updateChannels[key]; exists {
		return ch
	}

	ch := make(chan types.PriceUpdate, 100)
	s.updateChannels[key] = ch
	return ch
}

// RemoveUpdateChannel removes a price update channel
func (s *TradeStore) RemoveUpdateChannel(key string) {
	s.channelsMu.Lock()
	defer s.channelsMu.Unlock()

	if ch, exists := s.updateChannels[key]; exists {
		close(ch)
		delete(s.updateChannels, key)
	}
}
