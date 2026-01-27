● Done! Here are your IP-based URLs:                                                                               
  ┌─────────────────────┬─────────────────────────────────────────┐                                                                                                                       
  │         App         │                   URL                   │                                                                                                                         ├─────────────────────┼─────────────────────────────────────────┤
  │ WordPress           │ http://localhost:80 or http://localhost │                                                                                                                       
  ├─────────────────────┼─────────────────────────────────────────┤                                                                                                                       
  │ SignalManager       │ http://localhost:8001                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ SignalShot (Trader) │ http://localhost:8002                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ Admin               │ http://localhost:8003                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ PHPMyAdmin          │ http://localhost:8081                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ Redis Commander     │ http://localhost:8082                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ Mailpit             │ http://localhost:8025                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ NotifyGo API        │ http://localhost:8000                   │
  ├─────────────────────┼─────────────────────────────────────────┤
  │ Reverb WebSocket    │ http://localhost:8080                   │
  └─────────────────────┴─────────────────────────────────────────┘
  ---
  For production: On your server, you can remove the nginx service from docker-compose and use your existing nginx to proxy to the PHP-FPM containers directly on port 9000, or keep this 
  nginx and proxy to it from your main nginx. Would you like me to create a production docker-compose file without the nginx service?