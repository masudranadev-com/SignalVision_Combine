package api

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net/http"
	"signalnode/internal/logger"
	"signalnode/internal/store"
	"signalnode/internal/types"
	"strings"
	"time"
)

const (
	initialTradesURL = "https://manager.signalvision.ai/api/node/ini-trades"
	apiSecret        = "TRT56WTWRT"
	maxRetries       = 5
)

// FetchInitialTrades fetches initial trades from the server with retry
func FetchInitialTrades(tradeStore *store.TradeStore, scheduleSyncBinance, scheduleSyncBybit func()) error {
	for attempt := 1; attempt <= maxRetries; attempt++ {
		err := fetchOnce(tradeStore, scheduleSyncBinance, scheduleSyncBybit)
		if err == nil {
			return nil
		}

		delay := time.Duration(math.Min(30000, 1000*math.Pow(2, float64(attempt-1)))) * time.Millisecond
		logger.Log.Errorf("Initial trades attempt %d/%d failed: %v. Retrying in %v", attempt, maxRetries, err, delay)
		time.Sleep(delay)
	}

	logger.Log.Error("Initial trades failed after all retries; continuing without initial trades.")
	return fmt.Errorf("failed to fetch initial trades after %d attempts", maxRetries)
}

func fetchOnce(tradeStore *store.TradeStore, scheduleSyncBinance, scheduleSyncBybit func()) error {
	payload := map[string]string{"info": "OK"}
	payloadBytes, _ := json.Marshal(payload)

	req, err := http.NewRequest("POST", initialTradesURL, bytes.NewBuffer(payloadBytes))
	if err != nil {
		return err
	}

	req.Header.Set("API-SECRET", apiSecret)
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("unexpected status code: %d", resp.StatusCode)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}

	// Parse response
	var trades []map[string]interface{}

	// Try to unmarshal as array
	if err := json.Unmarshal(body, &trades); err != nil {
		// Try as string first
		var dataStr string
		if err2 := json.Unmarshal(body, &dataStr); err2 == nil {
			// Try to parse the string as JSON
			if err3 := json.Unmarshal([]byte(dataStr), &trades); err3 != nil {
				return fmt.Errorf("unexpected response shape: string - unparsable")
			}
		} else {
			return fmt.Errorf("unexpected response shape: %v", err)
		}
	}

	logger.Log.Infof("Initial trades fetched: %d", len(trades))

	// Add trades to store
	for _, t := range trades {
		trade := parseAPITrade(t)
		if trade != nil {
			tradeStore.AddOrUpdateTrade(trade)
		}
	}

	// Schedule stream sync for both exchanges
	if scheduleSyncBinance != nil {
		go scheduleSyncBinance()
	}
	if scheduleSyncBybit != nil {
		go scheduleSyncBybit()
	}

	return nil
}

func parseAPITrade(data map[string]interface{}) *types.Trade {
	trade := &types.Trade{}

	// Parse required fields
	if market, ok := data["market"].(string); ok {
		trade.Market = strings.ToLower(market)
	} else {
		trade.Market = types.MarketBinance
	}

	if mod, ok := data["mod"].(string); ok {
		trade.Mod = strings.ToUpper(mod)
	}

	if pair, ok := data["pair"].(string); ok {
		trade.Pair = strings.ToUpper(pair)
	}

	if tradeID, ok := data["trade_id"].(float64); ok {
		trade.TradeID = int(tradeID)
	}

	trade.Entry = getFloat(data, "entry")
	trade.SL = getFloat(data, "sl")
	trade.SLPercentage = getFloat(data, "sl_percentage")
	trade.SLPrice = getFloat(data, "sl_price")
	trade.TP1 = getFloat(data, "tp1")
	trade.TP2 = getFloat(data, "tp2")
	trade.TP3 = getFloat(data, "tp3")
	trade.TP4 = getFloat(data, "tp4")
	trade.TP5 = getFloat(data, "tp5")
	trade.TP6 = getFloat(data, "tp6")
	trade.TP7 = getFloat(data, "tp7")
	trade.TP8 = getFloat(data, "tp8")
	trade.TP9 = getFloat(data, "tp9")
	trade.TP10 = getFloat(data, "tp10")
	trade.HeightPrice = getFloat(data, "height_price")

	if status, ok := data["status"].(string); ok {
		trade.Status = status
	}

	if lastNotif, ok := data["last_notification"].(string); ok {
		trade.LastNotification = &lastNotif
	} else if data["last_notification"] == nil {
		trade.LastNotification = nil
	}

	return trade
}

func getFloat(data map[string]interface{}, key string) float64 {
	if val, ok := data[key]; ok && val != nil {
		switch v := val.(type) {
		case float64:
			if math.IsNaN(v) || math.IsInf(v, 0) {
				return 0
			}
			return v
		case string:
			var f float64
			fmt.Sscanf(v, "%f", &f)
			if math.IsNaN(f) || math.IsInf(f, 0) {
				return 0
			}
			return f
		}
	}
	return 0
}
