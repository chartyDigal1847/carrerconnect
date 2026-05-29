# CareerConnect Service - Architecture & Implementation

CareerConnect is a fully independent SOA-based Laravel 12 microservice within the DEORIS ecosystem, providing faculty communication, career support, and institutional coordination.

## Architecture Overview

### Service Identity
- **Service Name**: careerconnect-service
- **API Base**: `/api/v1/`
- **Database**: Isolated MySQL instance
- **Message Queue**: Redis-backed Laravel queues
- **Real-time**: Laravel Reverb WebSockets
- **Events**: SOA-based event outbox pattern

### Technology Stack
- **Framework**: Laravel 12
- **Database**: MySQL 8.0+
- **Cache**: Redis
- **Queues**: Redis/Laravel Queues
- **Broadcasting**: Laravel Reverb (WebSockets)
- **Frontend**: Single-Page Application (SPA)
- **API**: REST JSON

## Database Schema

### Core Tables

#### faculty_users
- SSO identity mapping
- Role-based access control
- Department associations
- Activity tracking

#### announcements
- Full-text searchable
- Role-based visibility
- Priority levels
- Expiration support

#### communication_boards
- Department-specific channels
- Post moderation support
- Member management
- Activity metrics

#### board_posts & board_comments
- Threaded discussions
- Approval workflow
- View/engagement tracking

#### career_resources
- Categorized resources
- File/link support
- Download tracking
- Featured resources

#### notifications
- Polymorphic notifications
- Read/archive states
- Real-time delivery

#### message_threads & messages
- Private faculty communication
- Multi-participant support
- Read receipts

#### event_outbox
- Exactly-once event delivery
- Retry mechanism
- Publication tracking

## Authentication & Authorization

### SSO Integration
1. Portal provides SSO token via `module-bridge.js`
2. Token validated with DEORIS Portal
3. Faculty user synchronized locally
4. Role-based permissions enforced

### Role-Based Access Control
```
Instructor:
  - Post announcements
  - Create boards/posts/comments
  - Access all resources
  - Participate in discussions

Cashier:
  - View announcements
  - Participate in discussions
  - Access resources

Librarian:
  - Upload resources
  - Participate in discussions
  - Share institutional materials

Admission Officer:
  - Create announcements
  - Manage boards
  - Access coordination tools

Admin:
  - Full system access
  - Moderation capabilities
  - Analytics access

BLOCKED: Student role (completely denied)
```

## REST API Endpoints

### Dashboard
```
GET /api/v1/dashboard/stats
GET /api/v1/dashboard/analytics (admin only)
```

### Announcements
```
GET    /api/v1/announcements
GET    /api/v1/announcements/{id}
POST   /api/v1/announcements
PUT    /api/v1/announcements/{id}
DELETE /api/v1/announcements/{id}
```

### Communication Boards
```
GET    /api/v1/boards
GET    /api/v1/boards/{id}
POST   /api/v1/boards
PUT    /api/v1/boards/{id}
DELETE /api/v1/boards/{id}

GET    /api/v1/boards/{board_id}/posts
GET    /api/v1/boards/{board_id}/posts/{id}
POST   /api/v1/boards/{board_id}/posts
PUT    /api/v1/posts/{id}
DELETE /api/v1/posts/{id}
```

### Career Resources
```
GET    /api/v1/resources
GET    /api/v1/resources/{id}
POST   /api/v1/resources
GET    /api/v1/resources/{id}/download
GET    /api/v1/resources/categories
```

### Notifications
```
GET    /api/v1/notifications
GET    /api/v1/notifications/unread-count
POST   /api/v1/notifications/{id}/read
POST   /api/v1/notifications/mark-all-read
POST   /api/v1/notifications/{id}/archive
```

### Messaging
```
GET    /api/v1/messages/threads
POST   /api/v1/messages/threads
GET    /api/v1/messages/threads/{id}
POST   /api/v1/messages/threads/{id}/send
POST   /api/v1/messages/{id}/read
```

### Search
```
GET    /api/v1/search?q={query}&type={type}
```

### Activity
```
GET    /api/v1/activity
GET    /api/v1/activity/system (admin only)
```

## Event-Driven Architecture

### Events Published to Hub
- `AnnouncementPublished`
- `PostCreated`
- `CommentAdded`
- `ResourceUploaded`
- `NotificationSent`

### Event Outbox Pattern
1. Event created locally with outbox entry
2. Async job publishes to Event Hub
3. HMAC-SHA256 signature verification
4. Retry mechanism (5 attempts, exponential backoff)
5. Marked as published on success

## Queue Jobs

### PublishEventToHub
- Publishes events to DEORIS Event Hub
- Handles signing and delivery
- Retry logic with exponential backoff

### SendNotification
- Creates notification records
- Dispatched in batches
- Handles user targeting

## Real-Time Features

### WebSocket Channels
- `announcements` - Public announcements
- `announcements.{dept_id}` - Department-specific
- `boards.{board_id}` - Board discussions
- `notifications.{user_id}` - Private notifications
- `messages.{thread_id}` - Message threads

### Broadcasting Events
- Real-time announcement delivery
- Live post updates
- Instant notifications
- Message synchronization

## Microservice Integration

### API-Only Communication
- No direct database sharing
- All requests through REST APIs
- Stateless design
- Event-driven updates

### Portal Integration
- SSO token exchange
- User role synchronization
- Portal origin validation
- CSRF protection

### Event Hub Integration
- Event publishing with signatures
- Correlation IDs for tracing
- Schema versioning
- Replay attack prevention

## Security Implementation

### Authentication Middleware
```php
ValidateSSOToken - Portal token validation
BlockStudents - Student role denial
CheckPermission - Role-based access
```

### Server-Side Validation
- All inputs validated
- XSS prevention (output escaping)
- SQL injection prevention (Eloquent ORM)
- CSRF token validation
- Rate limiting

### Headers & Policies
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Content-Security-Policy
- Secure cookie flags

## Deployment

### Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+
- Composer

### Environment Configuration
```
APP_NAME=CareerConnect
APP_ENV=production
APP_DEBUG=false
APP_URL=http://careerconnect.local
APP_PORTAL_URL=https://deoris.test

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=careerconnect
DB_USERNAME=cc_user
DB_PASSWORD=***

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=***

CAREERCONNECT_SERVICE_KEY=***
CAREERCONNECT_URL=http://careerconnect.local
EVENT_HUB_URL=http://event-hub.local/api/v1
EVENT_HUB_SECRET=***
```

### Installation Steps
```bash
# 1. Clone and setup
git clone <repo> careerconnect
cd careerconnect
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env with deployment values

# 3. Generate keys
php artisan key:generate

# 4. Database setup
php artisan migrate --force

# 5. Create admin user (optional seeder)
php artisan db:seed

# 6. Setup queues (Supervisor)
# Use supervisor to keep queue workers running

# 7. Setup WebSockets (if using Reverb)
php artisan reverb:start

# 8. Deploy on HTTPS
# Ensure SSL certificate is configured
```

### Queue Workers
```bash
# Supervisor configuration for queue workers
# /etc/supervisor/conf.d/careerconnect-queues.conf

[program:careerconnect-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/careerconnect/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/careerconnect-worker.log
```

## Monitoring & Analytics

### Metrics Available
- Announcement views & engagement
- Board post activity
- Resource download statistics
- User activity logs
- System performance

### Admin Dashboard
- Real-time statistics
- User activity monitoring
- Announcement engagement
- System health status

## API Documentation Format

All responses follow standard format:
```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7
  }
}
```

Errors:
```json
{
  "error": "Error message",
  "code": 400
}
```

## Performance Considerations

### Indexing Strategy
- Full-text indexes on searchable content
- Foreign key indexes for relationships
- Created_at indexes for sorting
- User_id indexes for filtering

### Caching
- Announcement counts (TTL: 5 minutes)
- Resource categories (TTL: 1 hour)
- User permissions (TTL: 30 minutes)
- Search results (TTL: 10 minutes)

### Query Optimization
- Eager loading of relationships
- Pagination for large datasets
- Database views for analytics
- Aggregation queries

## Troubleshooting

### Common Issues
1. SSO timeout - Check portal connectivity
2. Event publishing failure - Verify Event Hub URL and secret
3. Queue jobs not processing - Check Redis connection
4. WebSocket connection issues - Verify Reverb configuration

### Logs
- Application logs: `storage/logs/`
- Queue worker logs: Supervisor logs
- WebSocket logs: Reverb stdout

## Future Enhancements
- Email digest notifications
- Advanced search with filters
- Resource recommendations
- User preferences system
- Integration with external calendar services
