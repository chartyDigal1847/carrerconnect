# CareerConnect — Core Purpose

CareerConnect is the DEORIS faculty communication and career support service. Each core requirement maps to concrete system capabilities.

| Core purpose | Implementation |
|--------------|----------------|
| **Centralized faculty communication** | Faculty Hub dashboard, unified notifications, `/api/v1/dashboard/*`, SPA navigation |
| **Internal institutional announcements** | `announcements` module, priority/visibility, Event Hub outbox, auto-notifications |
| **Faculty collaboration** | Communication boards, posts, comments, board activity notifications |
| **Career guidance resources** | `career_resources` library, categories, approval workflow, download tracking |
| **Real-time communication updates** | `GET /api/v1/realtime/poll` (20s client polling), toast alerts, notification panel |
| **Role-based communication access** | `EnsureRole` middleware, `BlockStudents`, per-role permissions in `/auth/me` |
| **Secure institutional messaging** | Encrypted message content (`encrypted` cast), participant validation, private threads |
| **Department-wide coordination** | `GET /api/v1/departments`, department hub UI, scoped boards and announcements |

## API summary

```
GET  /api/v1/realtime/poll?since=ISO8601
GET  /api/v1/departments
GET  /api/v1/departments/{id}
GET  /api/v1/messages/directory
POST /api/v1/messages/threads
POST /api/v1/messages/threads/{id}/send
```

## Roles

| Role | Announcements | Boards | Resources | Messages |
|------|---------------|--------|-----------|----------|
| admin | ✓ | ✓ | ✓ | ✓ |
| instructor | ✓ | ✓ | ✓ | ✓ |
| admission_officer | ✓ | ✓ | — | ✓ |
| librarian | — | ✓ | ✓ | ✓ |
| cashier | — | ✓ | — | ✓ |
| **student** | **blocked** | **blocked** | **blocked** | **blocked** |
