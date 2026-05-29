# CareerConnect REST API

**Base URL:** `{service_url}/api/v1`  
**Auth:** `Authorization: Bearer {portal_sso_token}`  
**Format:** JSON  
**Rate limit:** 120 requests/minute per user (configurable)

## Service discovery

| Method | Path |
|--------|------|
| GET | `/api/health` |
| GET | `/api/v1/service/manifest` |
| GET | `/api/v1/service/openapi` |

## Core endpoints

### Announcements
- `GET /announcements` — list (paginated, filtered by visibility)
- `GET /announcements/{id}`
- `POST /announcements` — instructor, admission_officer, admin
- `PUT /announcements/{id}`
- `DELETE /announcements/{id}`

### Boards & posts
- `GET /boards`
- `POST /boards`
- `GET /boards/{id}/posts`
- `POST /boards/{id}/posts`
- `GET /boards/{board}/posts/{post}/comments`
- `POST /boards/{board}/posts/{post}/comments`

### Resources
- `GET /resources`
- `POST /resources` — librarian, instructor, admin
- `GET /resources/{id}/download`

### Notifications & messages
- `GET /notifications`
- `GET /messages/threads`
- `POST /messages/threads/{id}/send`

### Search & activity
- `GET /search?q=` — federated search
- `GET /activity`
- `GET /activity/system` — admin

### Analytics (admin)
- `GET /analytics/faculty-activity`
- `GET /analytics/announcement-delivery`
- `GET /analytics/engagement`

## Error responses

```json
{
  "error": "Human-readable message"
}
```

| Code | Meaning |
|------|---------|
| 401 | Missing/invalid SSO token |
| 403 | Student blocked or insufficient role |
| 422 | Validation failed |
| 429 | Rate limit exceeded |

## Students

All `/api/v1/*` routes return **403** for `student` role. Attempts are logged to `access_attempts`.
