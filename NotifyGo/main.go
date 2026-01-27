package main

import (
	"os"
	"os/signal"
	"signalnode/internal/api"
	"signalnode/internal/exchange"
	"signalnode/internal/logger"
	"signalnode/internal/notification"
	"signalnode/internal/store"
	"syscall"
)

func main() {
	// Initialize logger
	if err := logger.Init(); err != nil {
		panic(err)
	}
	defer logger.Close()

	logger.Log.Info("Starting SignalNode Go server...")

	// Create trade store
	tradeStore := store.NewTradeStore()

	// Create notification handler
	notifHandler := notification.NewHandler()
	notifHandler.Start()

	// Create exchange clients
	binanceClient := exchange.NewBinanceClient(tradeStore, notifHandler.Queue())
	bybitClient := exchange.NewBybitClient(tradeStore, notifHandler.Queue())

	// Start exchange clients
	binanceClient.Start()
	bybitClient.Start()

	// Create HTTP API server
	apiServer := api.NewServer(
		tradeStore,
		binanceClient.ScheduleSync,
		bybitClient.ScheduleSync,
	)

	// Fetch initial trades
	logger.Log.Info("Fetching initial trades...")
	if err := api.FetchInitialTrades(
		tradeStore,
		binanceClient.ScheduleSync,
		bybitClient.ScheduleSync,
	); err != nil {
		logger.Log.Warnf("Failed to fetch initial trades: %v", err)
	}

	// Setup graceful shutdown
	shutdown := make(chan os.Signal, 1)
	signal.Notify(shutdown, os.Interrupt, syscall.SIGTERM)

	// Start HTTP server in goroutine
	go func() {
		if err := apiServer.Start(8000); err != nil {
			logger.Log.Errorf("HTTP server error: %v", err)
			shutdown <- syscall.SIGTERM
		}
	}()

	logger.Log.Info("Server is running on port 8000")

	// Wait for shutdown signal
	<-shutdown

	logger.Log.Warn("Shutting down...")

	// Stop all components
	binanceClient.Stop()
	bybitClient.Stop()
	notifHandler.Stop()

	logger.Log.Info("Shutdown complete")
}
