# CareerConnect - Faculty Communication & Career Support Module

A fully independent SOA-based Laravel 12 microservice providing comprehensive faculty communication, career guidance, and institutional coordination within the DEORIS ecosystem.

## Overview

CareerConnect is a production-ready service featuring:

- **Faculty Communication**: Department-wide and institutional announcements with role-based visibility
- **Discussion Boards**: Moderated communication boards for faculty collaboration  
- **Career Resources**: Centralized repository of career development materials
- **Private Messaging**: Secure peer-to-peer faculty communication
- **Real-Time Updates**: WebSocket-based instant notifications and live feeds
- **Event-Driven Architecture**: SOA-based event publishing to centralized hub
- **Role-Based Access Control**: Instructor, Cashier, Librarian, Admission Officer, Admin roles
- **Strict Student Blocking**: Complete prevention of student access
- **Advanced Analytics**: System-wide monitoring and engagement metrics
- **Federated Search**: Full-text search across all content types

## Quick Start

### Development Setup (5 minutes)

```bash
# Clone repository
git clone <repo> careerconnect && cd careerconnect

# Install dependencies
composer install && npm install

# Setup environment
cp .env.example .env && php artisan key:generate

# Initialize database
php artisan migrate --force && php artisan db:seed

# Start servers (in separate terminals)
php artisan serve                    # Web server
php artisan queue:work redis         # Queue worker
php artisan reverb:start             # WebSockets
```

Visit `http://localhost:8000` to access the application.

### Production Deployment

See [SETUP.md](./SETUP.md) for complete production deployment instructions.

## Complete Documentation

- **[docs/API.md](./docs/API.md)** - REST API reference
- **[docs/DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md)** - Schema, views, procedures
- **[docs/EVENTS.md](./docs/EVENTS.md)** - Event Hub integration
- **[docs/REDIS_AND_QUEUES.md](./docs/REDIS_AND_QUEUES.md)** - Redis & queue workers
- **[SOA_ARCHITECTURE.md](./SOA_ARCHITECTURE.md)** - Microservice boundaries, integrations, deployment
- **[CAREERCONNECT_ARCHITECTURE.md](./CAREERCONNECT_ARCHITECTURE.md)** - System architecture, database schema, API reference
- **[CORE_PURPOSE.md](./CORE_PURPOSE.md)** - Core purpose requirements mapping
- **[SETUP.md](./SETUP.md)** - Installation and deployment guide

## Key Features

### 1. Announcements Management
- Create, edit, delete announcements
- Role-based and department-scoped visibility
- Priority levels and expiration support
- Full-text search capability
- View tracking and engagement metrics

### 2. Communication Boards
- Department-specific discussion channels
- Threaded conversations with nested replies
- Optional moderation workflow
- Post pinning and featured content
- Comment engagement tracking

### 3. Career Resources
- Categorized resource library
- Support for PDFs, links, videos, documents
- Resource approval workflow
- Download tracking and analytics
- Featured resource highlighting

### 4. Faculty Messaging
- Private multi-participant conversations
- Message threading
- Read receipts
- Secure communication

### 5. Real-Time Notifications
- Instant announcement delivery
- Post and comment notifications
- Message alerts
- Polymorphic notification system
- Read/archive states

### 6. Admin Analytics
- System-wide statistics dashboard
- Engagement metrics
- User activity monitoring
- Resource usage analytics

## Security

### Authentication & Authorization
- ✅ SSO token validation with DEORIS Portal
- ✅ Role-based access control (RBAC)
- ✅ **Complete student role blocking** - Students cannot access ANY functionality
- ✅ Department-scoped visibility
- ✅ Activity logging for audit trails

### Data Protection
- ✅ Server-side input validation
- ✅ XSS prevention via output escaping
- ✅ SQL injection prevention via ORM
- ✅ CSRF token protection
- ✅ Secure headers
- ✅ Rate limiting

## Technology Stack

See **[TECH_STACK.md](./TECH_STACK.md)** for the full stack reference.

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 |
| Database | MySQL 8.0+ |
| Cache / sessions | Redis 6.0+ |
| Queues | Laravel Queues + Redis |
| Events | Laravel Events + listeners |
| Broadcasting | Laravel Reverb (WebSockets) |
| API | REST JSON `/api/v1` |
| UI | Blade shell + responsive JavaScript SPA |
| Real-time client | Laravel Echo + Reverb |
| Authentication | DEORIS Portal SSO |

## Prerequisites

- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+
- Node.js 16+ (optional)
- Composer

## Installation

```bash
# 1. Clone and install
git clone <repo> careerconnect && cd careerconnect
composer install

# 2. Configure
cp .env.example .env
php artisan key:generate

# 3. Setup database
php artisan migrate

# 4. Seed data
php artisan db:seed

# 5. Start servers
php artisan serve
```

## API Reference

All endpoints require SSO authentication:

```bash
# Get announcements
GET /api/v1/announcements

# Create announcement (instructors only)
POST /api/v1/announcements

# Get discussion boards
GET /api/v1/boards

# Get career resources
GET /api/v1/resources

# Send message
POST /api/v1/messages/threads/{id}/send

# Search
GET /api/v1/search?q=query
```

Full API documentation in [CAREERCONNECT_ARCHITECTURE.md](./CAREERCONNECT_ARCHITECTURE.md#rest-api-endpoints)

## Project Structure

```
app/
├── Models/              # Eloquent models
├── Controllers/Api/     # API controllers
├── Events/             # Broadcasting events
├── Jobs/               # Queue jobs
├── Observers/          # Model observers
└── Http/Middleware/    # Custom middleware

database/
├── migrations/         # 13 schema migrations
└── seeders/           # Sample data

resources/
├── views/             # Blade templates
├── css/               # Stylesheets
└── js/                # Single-page app

routes/
├── api.php            # API routes
└── web.php            # Web routes
```

## Event-Driven Architecture

CareerConnect uses an **event outbox pattern** for reliable SOA communication:

1. Events created locally with outbox record
2. Async job publishes to Event Hub
3. HMAC-SHA256 signature verification
4. Automatic retry (5 attempts, exponential backoff)
5. Marked as published on success

**Events published:**
- `AnnouncementPublished`
- `PostCreated`
- `CommentAdded`
- `ResourceUploaded`
- `NotificationSent`

## Queue & Background Jobs

Queue workers handle:
- Event publishing to Event Hub
- Notification delivery
- Email dispatching
- Activity logging

```bash
# Start worker
php artisan queue:work redis

# Monitor queue
php artisan queue:monitor
```

## Real-Time Features

WebSocket channels (Laravel Reverb):
- `announcements` - Public announcements
- `announcements.{dept_id}` - Department-specific
- `boards.{board_id}` - Board discussions
- `notifications.{user_id}` - Private notifications
- `messages.{thread_id}` - Message threads

## Configuration

Key environment variables:

```env
APP_URL=http://careerconnect.local
APP_PORTAL_URL=https://deoris.test
DB_HOST=localhost
DB_DATABASE=careerconnect
REDIS_HOST=localhost
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb
EVENT_HUB_URL=http://event-hub.local/api/v1
```

See `.env.example` for all options.

## Monitoring & Logs

- **Application**: `storage/logs/laravel.log`
- **Queue Workers**: Supervisor logs
- **WebSockets**: Reverb output

## Deployment

### Development
```bash
php artisan serve
php artisan queue:work redis
php artisan reverb:start
```

### Production
- HTTPS only
- Database backups configured
- Supervisor manages queue workers
- Redis persistence enabled
- Nginx reverse proxy
- Monitoring and logging setup

See [SETUP.md](./SETUP.md) for complete production guide.

## Troubleshooting

**SSO Token Issues**
- Verify `APP_PORTAL_URL` in `.env`
- Check portal is accessible
- Review browser console

**Queue Jobs Not Processing**
- Ensure queue worker is running
- Check Redis connection
- Review logs

**WebSocket Issues**
- Verify Reverb is running
- Check firewall allows WebSocket
- Review browser network tab

See [SETUP.md#troubleshooting](./SETUP.md#troubleshooting) for more.

## Database Schema

Core tables (13 total):
- `faculty_users` - SSO-mapped faculty profiles
- `announcements` - Announcements with full-text search
- `communication_boards` - Discussion channels
- `board_posts` & `board_comments` - Threaded discussions
- `career_resources` - Resource library
- `message_threads` & `messages` - Faculty messaging
- `notifications` - Polymorphic notifications
- `activity_logs` - Audit trail
- `event_outbox` - SOA event queue

See [CAREERCONNECT_ARCHITECTURE.md#database-structure](./CAREERCONNECT_ARCHITECTURE.md#database-structure) for details.

## Role-Based Access Control

| Role | Permissions |
|------|-----------|
| **Admin** | Full system access, moderation, analytics |
| **Instructor** | Post announcements, create boards, upload resources |
| **Cashier** | View announcements, participate in discussions |
| **Librarian** | Upload resources, share materials |
| **Admission Officer** | Create announcements, manage boards |
| **Student** | ❌ **COMPLETELY BLOCKED** |

## Testing

```bash
php artisan test
php artisan test --coverage
```

## Contributing

1. Follow PSR-12 standards
2. Add tests for new features
3. Update documentation
4. Submit pull request

## License

Proprietary - DEORIS Ecosystem

## Support

- **Documentation**: See [CAREERCONNECT_ARCHITECTURE.md](./CAREERCONNECT_ARCHITECTURE.md)
- **Setup Guide**: See [SETUP.md](./SETUP.md)
- **Issues**: Contact DEORIS development team

---

**CareerConnect v1.0** - Faculty Communication & Career Support Module

```
Portal (DEORIS)
  └─ embeds CareerConnect via <iframe src="...?embedded=1">
       └─ https://deoris.test/module-bridge.js sends REQUEST_SSO via postMessage
            └─ Portal responds with SSO_TOKEN
                 └─ bridge POSTs token to portal /api/sso/exchange
                      └─ Portal validates its own session and token
                           └─ Returns { user }
                                └─ window.PORTAL_USER set in runtime memory only
                                     └─ module:ready fires -> app boots
```

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

Configure `.env`:

```env
APP_URL=https://careerconnect.deoris.test
APP_PORTAL_URL=https://deoris.test
DB_DATABASE=careerconnect
```

---

## Embedded Mode

CareerConnect runs inside the DEORIS Portal iframe. In production, `module-bridge.js` performs SSO via `postMessage`. In local development (`APP_ENV=local`), a dev token is used for standalone testing.
