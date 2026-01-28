# SignalVision - Complete URL Reference

## Base URL
```
http://76.13.13.232
https://76.13.13.232:8443
```

---

## Main Applications

### WordPress (Main Website)
- **URL:** http://76.13.13.232:8088
- **Port:** 8088
- **Service:** wordpress-app + nginx
- **Database:** signalvision

### Admin Panel
- **URL:** http://76.13.13.232:8091
- **Port:** 8091 (external) → 8001 (nginx)
- **Service:** admin-app + nginx
- **Database:** admin
- **Framework:** Laravel

### Manager Dashboard
- **URL:** http://76.13.13.232:8092
- **Port:** 8092 (external) → 8002 (nginx)
- **Service:** manager-app + nginx
- **Database:** manager
- **Framework:** Laravel

### Trader Platform
- **URL:** http://76.13.13.232:8093
- **Port:** 8093 (external) → 8003 (nginx)
- **Service:** trader-app + nginx
- **Database:** trader
- **Framework:** Laravel

---

## Additional Services

### NotifyGo API (Go Service)
- **URL:** http://76.13.13.232:8004
- **Port:** 8004
- **Service:** notifygo
- **Protocol:** HTTP REST API
- **Health Check:** http://76.13.13.232:8004/health

### Manager Reverb (WebSocket)
- **URL:** ws://76.13.13.232:8085
- **Port:** 8085
- **Service:** manager-reverb
- **Protocol:** WebSocket
- **Purpose:** Real-time messaging for Manager app

### HTTPS/SSL
- **URL:** https://76.13.13.232:8443
- **Port:** 8443
- **Certificate Path:** docker/nginx/ssl/

---

## Database Connections

### MySQL
- **Host:** 76.13.13.232
- **Port:** 3306
- **Root User:** root
- **Root Password:** ${MYSQL_ROOT_PASSWORD}
- **App User:** signalvision
- **App Password:** ${MYSQL_PASSWORD}

**Connection String (Laravel):**
```
mysql://signalvision:signalvision_pass@76.13.13.232:3306/admin
mysql://signalvision:signalvision_pass@76.13.13.232:3306/manager
mysql://signalvision:signalvision_pass@76.13.13.232:3306/trader
mysql://signalvision:signalvision_pass@76.13.13.232:3306/signalvision
```

### Redis
- **Host:** 76.13.13.232
- **Port:** 6380
- **Password:** ${REDIS_PASSWORD}
- **Database:** 0 (default)

**Connection String:**
```
redis://:redispassword@76.13.13.232:6380/0
```

---

## API Endpoints Summary

| Service | URL | Port | Type | Purpose |
|---------|-----|------|------|---------|
| WordPress | http://76.13.13.232:8088 | 8088 | Web | Main website |
| Admin Panel | http://76.13.13.232:8091 | 8091 | Web | Admin dashboard |
| Manager | http://76.13.13.232:8092 | 8092 | Web | Manager dashboard |
| Trader | http://76.13.13.232:8093 | 8093 | Web | Trading platform |
| NotifyGo API | http://76.13.13.232:8004 | 8004 | REST API | Notification service |
| Manager Reverb | ws://76.13.13.232:8085 | 8085 | WebSocket | Real-time messaging |
| HTTPS | https://76.13.13.232:8443 | 8443 | HTTPS | Secure connection |
| MySQL | 76.13.13.232:3306 | 3306 | Database | Data storage |
| Redis | 76.13.13.232:6380 | 6380 | Cache | Session/cache store |

---

## Development Tools (Profile: dev)

To enable development tools, run:
```bash
docker-compose up -d --profile dev
```

### phpMyAdmin
- **URL:** http://76.13.13.232:8081
- **Port:** 8081
- **Purpose:** MySQL management UI

### Redis Commander
- **URL:** http://76.13.13.232:8082
- **Port:** 8082
- **Purpose:** Redis management UI

### Mailpit (Email Testing)
- **URL:** http://76.13.13.232:8025
- **SMTP:** 76.13.13.232:1025
- **Port (UI):** 8025
- **Port (SMTP):** 1025

---

## Internal Service Hostnames (Docker Network)

When connecting from within Docker containers, use internal hostnames:

```
mysql:3306          (MySQL)
redis:6379          (Redis - internal port)
admin-app:9000      (Admin PHP-FPM)
manager-app:9000    (Manager PHP-FPM)
trader-app:9000     (Trader PHP-FPM)
wordpress-app:9000  (WordPress PHP-FPM)
notifygo:8000       (NotifyGo API)
nginx:80            (Nginx web server)
```

---

## Environment Variables

Create `.env` file in project root:

```env
# Database
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_USER=signalvision
MYSQL_PASSWORD=signalvision_pass

# Redis
REDIS_PASSWORD=redispassword

# Application
APP_ENV=production
APP_DEBUG=false
GIN_MODE=release
```

---

## Quick Access Checklist

- [ ] WordPress Main: http://76.13.13.232:8088
- [ ] Admin Panel: http://76.13.13.232:8091
- [ ] Manager: http://76.13.13.232:8092
- [ ] Trader: http://76.13.13.232:8093
- [ ] NotifyGo API: http://76.13.13.232:8004
- [ ] Manager WebSocket: ws://76.13.13.232:8085
- [ ] MySQL: 76.13.13.232:3306
- [ ] Redis: 76.13.13.232:6380
- [ ] HTTPS: https://76.13.13.232:8443

---

## Startup Commands

```bash
# Start all services
docker-compose -f docker-compose.prod.yml up -d

# Start with development tools
docker-compose -f docker-compose.yml up -d --profile dev

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Stop all services
docker-compose -f docker-compose.prod.yml down

# Restart specific service
docker-compose -f docker-compose.prod.yml restart admin-app
```
