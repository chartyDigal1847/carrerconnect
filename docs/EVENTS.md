# CareerConnect Event Hub Integration

**Source service:** `careerconnect-service`  
**Transport:** HTTP POST to Event Hub  
**Pattern:** Transactional outbox + queue `careerconnect.events`

## Required events

| Event | Trigger |
|-------|---------|
| `AnnouncementPublished` | Announcement published (observer) |
| `CommunicationBoardCreated` | New board created |
| `ResourceUploaded` | Career resource created |
| `NotificationSent` | Notification queued for user |
| `FacultyMessagePosted` | Secure message sent |

## Envelope fields

Each outbox record includes:

| Field | Description |
|-------|-------------|
| `event_id` | UUID |
| `event_name` | Event type |
| `source_service` | `careerconnect-service` |
| `payload` | Domain JSON |
| `schema_version` | `1.0` |
| `correlation_id` | Trace UUID |
| `published_at` | ISO8601 (in signed envelope) |

## Security

- **HMAC-SHA256** signature over `timestamp|nonce|payload`
- **Nonce** stored in Redis (replay prevention)
- **Timestamp** skew validation (default 300s)
- Headers: `X-Event-Signature`, `X-Event-Timestamp`, `X-Event-Nonce`, `X-Service-Key`

## Configuration

```env
EVENT_HUB_URL=http://event-hub.local/api/v1
EVENT_HUB_SECRET=your-secret
CAREERCONNECT_SERVICE_KEY=your-service-key
```
