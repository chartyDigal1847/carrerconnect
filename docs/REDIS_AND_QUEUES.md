# Redis & Queue Configuration

## Redis usage

| Purpose | Config key |
|---------|------------|
| Queues | `QUEUE_CONNECTION=redis` |
| Cache | `CACHE_STORE=redis` |
| Sessions | `SESSION_DRIVER=redis` |
| Broadcasting | Reverb (WebSocket) |
| Event nonce store | `careerconnect:event_nonce:*` |

## Redis channels (logical)

| Channel | Key |
|---------|-----|
| Events | `careerconnect.events` |
| Notifications | `careerconnect.notifications` |
| Broadcast | `careerconnect.broadcast` |
| Cache | `careerconnect.cache` |

## Queue workers

Run workers per queue:

```bash
php artisan queue:work redis --queue=careerconnect.events
php artisan queue:work redis --queue=careerconnect.notifications
php artisan queue:work redis --queue=careerconnect.communications
php artisan queue:work redis --queue=careerconnect.moderation
```

### Supervisor example

```ini
[program:careerconnect-events]
command=php /var/www/careerconnect/artisan queue:work redis --queue=careerconnect.events --sleep=3 --tries=5
autostart=true
autorestart=true
numprocs=1
```

## Docker

`docker compose up` starts `queue` and `reverb` services alongside `redis`.
