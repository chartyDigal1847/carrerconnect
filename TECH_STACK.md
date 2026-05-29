# CareerConnect — Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| **Framework** | Laravel 12 | SOA microservice, REST API, events, queues |
| **Database** | MySQL 8.0+ | Isolated `careerconnect` schema |
| **Cache / sessions** | Redis | Cache, sessions (production), queue backend |
| **Queues** | Laravel Queues + Redis | Event Hub publish, notifications, broadcasts |
| **Events** | Laravel Events + Listeners | Domain reactions, outbox, notifications |
| **Broadcasting** | Laravel Broadcasting + **Reverb** | WebSocket real-time updates |
| **API** | REST JSON `/api/v1` | Portal & module integration |
| **UI** | **Blade** shell + JavaScript SPA | Embedded DEORIS module (no build step required) |
| **WebSockets** | Laravel Echo + Pusher protocol (Reverb) | Live announcements, notifications, board posts, messages |
| **Frontend** | Responsive CSS (`careerconnect.css`) | Mobile-friendly faculty UI |

## Runtime processes

```bash
# Web
php artisan serve

# WebSockets (Reverb)
php artisan reverb:start

# Queue worker (Redis)
php artisan queue:work redis

# Scheduler (optional)
php artisan schedule:work
```

## Docker (full stack)

```bash
docker compose up -d
# Services: app, queue, mysql, redis, reverb
```

## Environment

```env
DB_CONNECTION=mysql
DB_DATABASE=careerconnect

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=careerconnect
REVERB_APP_KEY=careerconnect-key
REVERB_APP_SECRET=careerconnect-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Real-time channels

| Channel | Type | Events |
|---------|------|--------|
| `announcements` | Public | `announcement.published` |
| `announcements.department.{id}` | Public | `announcement.published` |
| `boards.{id}` | Public | `post.created` |
| `notifications.{userId}` | Private | `notification.created` |
| `messages.thread.{id}` | Private | `message.sent` |

HTTP polling (`/api/v1/realtime/poll`) remains as fallback when Reverb is offline.

## Optional: Inertia + Vue

The current UI uses a **Blade-hosted SPA** (recommended for DEORIS iframe modules). To migrate to Inertia:

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3 vue
php artisan inertia:middleware
```

Keep API boundaries unchanged — Inertia would consume the same `/api/v1` or use Laravel controllers directly.
