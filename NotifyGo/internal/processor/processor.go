package processor

import (
	"math"
	"signalnode/internal/types"
)

// ProcessTrades processes price updates for trades (equivalent to tradeWorker.js)
func ProcessTrades(symbol string, price float64, trades []*types.Trade, market string) types.ProcessResult {
	result := types.ProcessResult{
		Symbol:         symbol,
		Notifications:  []types.NotificationEvent{},
		RemoveTradeIDs: []int{},
		Patches:        []types.TradePatch{},
	}

	for _, curE := range trades {
		curE.RLock()
		status := curE.Status
		mod := curE.Mod
		entry := curE.Entry
		sl := curE.SL
		tradeID := curE.TradeID
		lastNotif := curE.LastNotification
		curE.RUnlock()

		if status == types.StatusWaiting {
			// Check entry hit
			entryHit := false
			if mod == types.ModeLong {
				entryHit = price <= entry
			} else {
				entryHit = price >= entry
			}

			if entryHit {
				result.Notifications = append(result.Notifications, types.NotificationEvent{
					TradeID: tradeID,
					Type:    "ENTRY",
					Price:   price,
				})
				result.Patches = append(result.Patches, types.TradePatch{
					TradeID: tradeID,
					Status:  types.StatusRunning,
				})
			}
		} else if status == types.StatusRunning {
			// Check SL hit
			slHit := false
			if mod == types.ModeLong {
				slHit = price <= sl
			} else {
				slHit = price >= sl
			}

			if slHit {
				result.Notifications = append(result.Notifications, types.NotificationEvent{
					TradeID: tradeID,
					Type:    "SL",
					Price:   price,
				})
				result.RemoveTradeIDs = append(result.RemoveTradeIDs, tradeID)
			} else {
				// Check TPs (tp10 -> tp1 priority)
				curE.RLock()
				levels := []struct {
					name  string
					value float64
				}{
					{"TP10", curE.TP10},
					{"TP9", curE.TP9},
					{"TP8", curE.TP8},
					{"TP7", curE.TP7},
					{"TP6", curE.TP6},
					{"TP5", curE.TP5},
					{"TP4", curE.TP4},
					{"TP3", curE.TP3},
					{"TP2", curE.TP2},
					{"TP1", curE.TP1},
				}
				curE.RUnlock()

				var currentTP *string
				for _, level := range levels {
					if !isFinite(level.value) || level.value == 0 {
						continue
					}

					hit := false
					if mod == types.ModeLong {
						hit = price >= level.value
					} else {
						hit = price <= level.value
					}

					if hit {
						tp := level.name
						currentTP = &tp
						break
					}
				}

				if currentTP != nil {
					if lastNotif == nil || *lastNotif != *currentTP {
						result.Notifications = append(result.Notifications, types.NotificationEvent{
							TradeID: tradeID,
							Type:    *currentTP,
							Price:   price,
						})
						result.Patches = append(result.Patches, types.TradePatch{
							TradeID:          tradeID,
							LastNotification: currentTP,
						})
					}
				} else if lastNotif != nil {
					var nilStr *string
					result.Patches = append(result.Patches, types.TradePatch{
						TradeID:          tradeID,
						LastNotification: nilStr,
					})
				}
			}
		}
	}

	return result
}

// CrossedDown checks if price crossed down through a level
func CrossedDown(prev, curr, level float64) bool {
	return prev > level && curr <= level
}

// CrossedUp checks if price crossed up through a level
func CrossedUp(prev, curr, level float64) bool {
	return prev < level && curr >= level
}

// HasCriticalCross checks if there's a critical price cross that requires processing
func HasCriticalCross(trades []*types.Trade, prev, curr float64) bool {
	if !isFinite(prev) {
		return true // First seen
	}

	for _, t := range trades {
		t.RLock()
		status := t.Status
		mod := t.Mod
		entry := t.Entry
		sl := t.SL
		tp10 := t.TP10
		tp9 := t.TP9
		tp8 := t.TP8
		tp7 := t.TP7
		tp6 := t.TP6
		tp5 := t.TP5
		tp4 := t.TP4
		tp3 := t.TP3
		tp2 := t.TP2
		tp1 := t.TP1
		t.RUnlock()

		if status == types.StatusWaiting {
			if mod == types.ModeLong && CrossedDown(prev, curr, entry) {
				return true
			}
			if mod == types.ModeShort && CrossedUp(prev, curr, entry) {
				return true
			}
		} else if status == types.StatusRunning {
			if mod == types.ModeLong && CrossedDown(prev, curr, sl) {
				return true
			}
			if mod == types.ModeShort && CrossedUp(prev, curr, sl) {
				return true
			}

			levels := []float64{tp10, tp9, tp8, tp7, tp6, tp5, tp4, tp3, tp2, tp1}
			for _, lv := range levels {
				if !isFinite(lv) {
					continue
				}
				if mod == types.ModeLong && CrossedUp(prev, curr, lv) {
					return true
				}
				if mod == types.ModeShort && CrossedDown(prev, curr, lv) {
					return true
				}
			}
		}
	}

	return false
}

// isFinite checks if a float64 is finite
func isFinite(f float64) bool {
	return !math.IsNaN(f) && !math.IsInf(f, 0)
}
