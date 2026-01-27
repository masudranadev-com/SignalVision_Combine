#!/bin/bash
# Supervisor Deployment Script for signalmanager-node
# Run this script to rebuild and restart the service after code changes

set -e

echo "🔨 Building optimized binary..."
/usr/bin/go build -o signalmanager-node -ldflags="-s -w" main.go

echo "📦 Binary built successfully ($(du -h signalmanager-node | cut -f1))"

echo "🔄 Restarting service..."
sudo supervisorctl restart signalmanager-node

echo "✅ Deployment complete!"
echo ""
echo "📊 Service status:"
sudo supervisorctl status signalmanager-node

echo ""
echo "📝 View logs with: sudo tail -f /home/signalvision-node/logs/signalmanager-node.log"
