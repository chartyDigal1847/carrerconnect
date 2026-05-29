# CareerConnect Setup Guide

Complete setup instructions for CareerConnect service in the DEORIS ecosystem.

## Prerequisites

- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher
- **Redis**: 6.0 or higher
- **Node.js**: 16.0 or higher (optional, for frontend development)
- **Composer**: Latest version
- **Git**: For version control

## Installation Steps

### 1. Clone Repository

```bash
git clone <repository-url> careerconnect
cd careerconnect
```

### 2. Install Dependencies

```bash
# PHP dependencies
composer install

# Node.js dependencies (optional)
npm install
```

### 3. Environment Configuration

```bash
# Copy example env file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Edit `.env` with your configuration:

```env
APP_NAME="CareerConnect"
APP_ENV=local
APP_URL=http://careerconnect.local
APP_PORTAL_URL=https://deoris.test

# Database
DB_HOST=127.0.0.1
DB_DATABASE=careerconnect
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Broadcasting
BROADCAST_DRIVER=reverb

# Services
CAREERCONNECT_SERVICE_KEY=your-service-key
EVENT_HUB_URL=http://event-hub.local/api/v1
EVENT_HUB_SECRET=your-event-hub-secret
```

### 4. Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE careerconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed
```

### 5. Development Server

```bash
# Terminal 1: Web server
php artisan serve
# Runs on http://localhost:8000

# Terminal 2: Queue worker
php artisan queue:work redis

# Terminal 3: WebSocket server (optional)
php artisan reverb:start
# Runs on ws://localhost:8080
```

### 6. Verify Installation

Visit `http://localhost:8000` in your browser. You should see:
- CareerConnect loading page
- Initial SSO authentication
- Dashboard upon successful authentication

## Production Deployment

### Prerequisites

- Production server with HTTPS
- SSL certificate
- Database backups configured
- Redis persistence configured

### Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://careerconnect.yourdomain.com
APP_PORTAL_URL=https://portal.yourdomain.com

# Secure defaults
SESSION_SECURE_COOKIES=true
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb
```

### Database

```bash
# Create production database
mysql -u root -p -e "CREATE DATABASE careerconnect_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate --env=production --force

# Seed if needed
php artisan db:seed --env=production
```

### Queue Workers

Use Supervisor to manage queue workers:

File: `/etc/supervisor/conf.d/careerconnect-queue.conf`

```ini
[program:careerconnect-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/careerconnect/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/careerconnect-queue.log
```

Then reload:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start careerconnect-queue:*
```

### WebSocket Server

Use Supervisor or systemd to manage Reverb:

```bash
# Start on system boot
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name careerconnect.yourdomain.com;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    root /var/www/careerconnect/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket configuration
    location /app/ {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Static files
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

## Troubleshooting

### Database Connection Error

```bash
# Check MySQL is running
mysql -u root -p -e "SELECT 1"

# Verify credentials in .env
# Check database exists: mysql -u root -p -e "SHOW DATABASES;"
```

### Redis Connection Error

```bash
# Check Redis is running
redis-cli ping
# Should output: PONG

# Verify Redis config in .env
```

### Queue Jobs Not Processing

```bash
# Check queue worker is running
ps aux | grep "queue:work"

# Monitor queue
php artisan queue:monitor

# Debug specific job
php artisan tinker
>>> Queue::fake(); // For testing
```

### WebSocket Connection Issues

```bash
# Check Reverb is running
ps aux | grep reverb

# Test WebSocket connection
# Use browser DevTools to inspect WebSocket tab
```

### SSO Token Issues

```bash
# Check portal URL in .env
# Verify portal is accessible
# Check browser console for SSO errors

# Monitor logs
tail -f storage/logs/laravel.log
```

## Monitoring & Maintenance

### Logs

```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor queue logs
tail -f /var/log/careerconnect-queue.log

# Monitor WebSocket logs (if using systemd)
journalctl -u careerconnect-reverb -f
```

### Database Maintenance

```bash
# Backup database
mysqldump -u root -p careerconnect > backup_$(date +%Y%m%d).sql

# Optimize database
php artisan tinker
>>> DB::statement('OPTIMIZE TABLE announcements, board_posts, career_resources, faculty_users');

# Clear old activity logs
php artisan tinker
>>> ActivityLog::where('created_at', '<', now()->subMonths(6))->delete();
```

### Cache Management

```bash
# Clear all cache
php artisan cache:clear

# Clear specific cache tags
php artisan cache:forget announcements_count

# Monitor cache
php artisan tinker
>>> Cache::store('redis')->keys();
```

## API Testing

### Announcements

```bash
# Get announcements
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/v1/announcements

# Create announcement
curl -X POST -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","content":"Test content","priority":"normal"}' \
  http://localhost:8000/api/v1/announcements
```

### Boards

```bash
# Get boards
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/v1/boards

# Get board posts
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8000/api/v1/boards/1/posts
```

## Security Checklist

- [ ] Changed all default credentials
- [ ] Configured firewall rules
- [ ] Set up HTTPS/SSL
- [ ] Enabled database backups
- [ ] Configured rate limiting
- [ ] Set up logging and monitoring
- [ ] Reviewed user permissions
- [ ] Configured CORS headers
- [ ] Set up intrusion detection
- [ ] Reviewed and updated dependencies

## Performance Optimization

1. **Database Indexing**: Already configured in migrations
2. **Query Optimization**: Use `select()` to fetch only needed columns
3. **Caching**: Cache expensive queries (announcements, resources)
4. **Redis**: Use for sessions, cache, and queues
5. **Asset Compression**: Enable Gzip in Nginx
6. **Database Queries**: Monitor with Laravel Debugbar in development

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review API documentation: `CAREERCONNECT_ARCHITECTURE.md`
3. Check DEORIS Portal integration docs
4. Review environment configuration

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Reverb (WebSockets)](https://laravel.com/docs/reverb)
- [Redis Documentation](https://redis.io/documentation)
- [DEORIS Portal Integration Guide]()

