package notification

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"runtime"
	"signalnode/internal/logger"
	"signalnode/internal/types"
	"sync"
	"time"
)

const (
	notificationURL = "https://manager.signalvision.ai/api/node/notification"
	apiSecret       = "TRT56WTWRT"
	queueSize       = 10000      // Larger queue for high throughput
	requestTimeout  = 10 * time.Second
)

// getOptimalWorkers calculates optimal worker count based on CPU cores
// For 8 cores: 8 * 4 = 32 workers (good for I/O-bound HTTP requests)
func getOptimalWorkers() int {
	cores := runtime.NumCPU()
	workers := cores * 4 // 4x cores for I/O-bound work
	if workers < 10 {
		workers = 10 // Minimum 10 workers
	}
	if workers > 100 {
		workers = 100 // Cap at 100 to avoid resource exhaustion
	}
	return workers
}

// Handler manages notification sending with queue and workers
type Handler struct {
	queue    chan types.Notification
	shutdown chan struct{}
	wg       sync.WaitGroup
	client   *http.Client
}

// NewHandler creates a new notification handler
func NewHandler() *Handler {
	return &Handler{
		queue:    make(chan types.Notification, queueSize),
		shutdown: make(chan struct{}),
		client: &http.Client{
			Timeout: requestTimeout,
		},
	}
}

// Start starts the notification workers
func (h *Handler) Start() {
	numWorkers := getOptimalWorkers()
	for i := 0; i < numWorkers; i++ {
		h.wg.Add(1)
		go h.worker()
	}
	logger.Log.Infof("Started %d notification workers (auto-scaled for %d CPU cores)", numWorkers, runtime.NumCPU())
}

// Stop stops the notification handler
func (h *Handler) Stop() {
	close(h.shutdown)
	h.wg.Wait()
	logger.Log.Info("Notification handler stopped")
}

// Queue returns the notification queue channel
func (h *Handler) Queue() chan types.Notification {
	return h.queue
}

func (h *Handler) worker() {
	defer h.wg.Done()

	for {
		select {
		case <-h.shutdown:
			return
		case notif := <-h.queue:
			h.send(notif)
		}
	}
}

func (h *Handler) send(notif types.Notification) {
	data := map[string]any{
		"id":            notif.TradeID,
		"type":          notif.Type,
		"current_price": notif.Price,
		"data":          notif.Data,
	}

	// Log the notification
	dataJSON, _ := json.Marshal(data)
	fmt.Println(string(dataJSON))

	payload, err := json.Marshal(data)
	if err != nil {
		logger.Log.Errorf("Failed to marshal notification: %v", err)
		return
	}

	req, err := http.NewRequest("POST", notificationURL, bytes.NewBuffer(payload))
	if err != nil {
		logger.Log.Errorf("Failed to create notification request: %v", err)
		return
	}

	req.Header.Set("API-SECRET", apiSecret)
	req.Header.Set("Content-Type", "application/json")

	resp, err := h.client.Do(req)
	if err != nil {
		logger.Log.Errorf("Failed to send notification: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		logger.Log.Warnf("Notification returned status %d for trade %d", resp.StatusCode, notif.TradeID)
	}
}
