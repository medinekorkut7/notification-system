# Event-Driven Notification System

![CI](https://github.com/your-org/your-repo/actions/workflows/ci.yml/badge.svg)

Scalable, multi-channel notification system built with Laravel 12. Handles high throughput, rate limiting, retries, and real-time status tracking for SMS, Email, and Push channels.

Architecture: see `ARCHITECTURE.md`.

## Features (Non-Technical Summary)
- **Multi-Channel Support**: SMS, Email, and Push notifications
- **Batch Processing**: Up to 1000 notifications per request
- **Priority Queues**: High, Normal, and Low priority levels
- **Rate Limiting**: Configurable per-channel rate limits (default: 100 msg/sec)
- **Intelligent Retry**: Exponential backoff with configurable max attempts
- **Scheduled Notifications**: Schedule notifications for future delivery
- **Template System**: Reusable message templates with variable substitution
- **Idempotency Support**: Prevent duplicate sends using idempotency keys
- **Real-time Metrics**: Queue depth, success/failure rates, latency stats
- **Correlation ID Tracking**: Distributed tracing support
- **Health Checks**: Database, Redis, and queue health monitoring

## Architectural

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                 API Layer                                   │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐ │
│  │ Notification  │  │   Template    │  │    Metrics    │  │    Health     │ │
│  │  Controller   │  │  Controller   │  │  Controller   │  │  Controller   │ │
│  └──────┬────────┘  └──────┬────────┘  └──────┬────────┘  └───────────────┘ │
└─────────┼──────────────────┼──────────────────┼────────────────────────────┘
          │                  │                  │
          ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                               Service Layer                                 │
│  ┌────────────────────┐  ┌──────────────────┐  ┌───────────────────────┐   │
│  │ NotificationProvider│  │  RetryPolicy     │  │   CircuitBreaker      │   │
│  │                    │  │                  │  │                       │   │
│  └─────────┬──────────┘  └─────────┬────────┘  └─────────┬─────────────┘   │
└────────────┼──────────────────────┼──────────────────────┼─────────────────┘
             │                      │                      │
             ▼                      ▼                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Queue Layer (Redis/DB)                               │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │notifications-high│  │notifications-norm│  │notifications-low │          │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘          │
└───────────┼─────────────────────┼─────────────────────┼────────────────────┘
            │                     │                     │
            └─────────────────────┼─────────────────────┘
                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            Processing Layer                                  │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                         SendNotificationJob                            │ │
│  │  ┌──────────────┐  ┌───────────────┐  ┌───────────────────────────┐    │ │
│  │  │Rate Limit +  │─▶│  Provider     │─▶│ NotificationAttempt Log   │    │ │
│  │  │Circuit Breaker│  │  Webhook     │  │  (Correlation/Trace ID)   │    │ │
│  │  └──────────────┘  └───────────────┘  └───────────────────────────┘    │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           External Provider                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Tech Stack
- Laravel 12 (PHP)
- MySQL + Redis
- Docker Compose

## Quick Start (Docker)
1) Configure environment:
```bash
cp .env.example .env
```

2) Set your webhook URL in `.env`:
```
NOTIFICATION_PROVIDER_WEBHOOK_URL=https://webhook.site/your-uuid
```

3) Start services:
```bash
docker compose up -d
```

4) Run migrations:
```bash
docker compose exec app php artisan migrate
```

5) Start workers (one per priority lane):
```bash
docker compose exec app php artisan queue:work --queue=notifications-high
```
```bash
docker compose exec app php artisan queue:work --queue=notifications-normal
```
```bash
docker compose exec app php artisan queue:work --queue=notifications-low
```

6) Scheduler for scheduled notifications:
```bash
docker compose exec app php artisan schedule:work
```

API base URL: `http://localhost:8000/api/v1`

Authentication: set `API_KEYS` in `.env`, then send `X-Api-Key` on every request.
Example: `API_KEYS=local-dev-key`

Admin UI: `http://localhost:8000/admin`

Supervisor-managed workers are bundled in the Docker image. Use the Admin panel to start/stop worker processes.

### Admin Panel Overview
- **API Key Setup**: store the `X-Api-Key` used by panel actions.
- **Provider Settings**: set the primary and fallback webhook URLs (overrides `.env` at runtime).
- **Worker Control**: pause/resume processing, start/stop workers, and restart workers.
- **Stress Test**: generate load for local testing.
- **Failure Analytics**: view top error codes and permanent failure breakdowns.
- **Admin Users**: manage who can access the panel.

Admin UI uses its own `admin_users` table. Create the first admin with:
```bash
docker compose exec app php artisan tinker
>>> \App\Models\AdminUser::create([
... 'name' => 'Admin',
... 'email' => 'admin@example.com',
... 'password' => \Illuminate\Support\Facades\Hash::make('changeme'),
... 'role' => 'admin',
... 'is_active' => true,
... ]);
```

Login with the email/password you created, then the Admin Users section shows who can access the panel.

Quick seed (recommended for dev):
```bash
docker compose exec app php artisan db:seed
```
Defaults (override in `.env`):
```
ADMIN_SEED_NAME=Admin
ADMIN_SEED_EMAIL=admin@example.com
ADMIN_SEED_PASSWORD=changeme
```

## API Examples
Create batch notifications:
```bash
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: local-dev-key" \
  -H "X-Correlation-Id: demo-123" \
  -d '{
    "batch": {"idempotency_key": "batch-1"},
    "notifications": [
      {"recipient": "+905551234567", "channel": "sms", "content": "Flash sale!", "priority": "high"},
      {"recipient": "user@example.com", "channel": "email", "content": "Welcome!"}
    ]
  }'
```

Get notification status:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/notifications/{notificationId}
```

Get batch status:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/batches/{batchId}
```

Cancel a notification:
```bash
curl -X POST -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/notifications/{notificationId}/cancel
```

List notifications:
```bash
curl -H "X-Api-Key: local-dev-key" "http://localhost:8000/api/v1/notifications?status=sent&channel=sms&per_page=20"
```

Metrics:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/metrics
```

Prometheus metrics:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/metrics/prometheus
```

Health:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/health
```

Dead-letter list:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/dead-letter
```

Dead-letter item:
```bash
curl -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/dead-letter/{deadLetterId}
```

Requeue dead-letter item:
```bash
curl -X POST -H "X-Api-Key: local-dev-key" "http://localhost:8000/api/v1/dead-letter/{deadLetterId}/requeue?priority=high&delay_seconds=30"
```

Requeue dead-letter items (bulk):
```bash
curl -X POST -H "X-Api-Key: local-dev-key" "http://localhost:8000/api/v1/dead-letter/requeue?limit=100&channel=sms&priority=low&delay_seconds=60"
```

Templates:
```bash
curl -X POST http://localhost:8000/api/v1/templates \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: local-dev-key" \
  -d '{"name":"welcome","channel":"sms","content":"Hello {{name}}","default_variables":{"name":"Guest"}}'
```

Update template:
```bash
curl -X PATCH http://localhost:8000/api/v1/templates/{templateId} \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: local-dev-key" \
  -d '{"content":"Hello {{name}}, updated!"}'
```

Delete template:
```bash
curl -X DELETE -H "X-Api-Key: local-dev-key" http://localhost:8000/api/v1/templates/{templateId}
```

Preview template:
```bash
curl -X POST http://localhost:8000/api/v1/templates/preview \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: local-dev-key" \
  -d '{"template_id":"{templateId}","variables":{"name":"Ada"}}'
```

Send with template:
```bash
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: local-dev-key" \
  -d '{"notifications":[{"recipient":"+905551234567","channel":"sms","template_id":"{templateId}","variables":{"name":"Ada"}}]}'
```

## Response Samples
POST /api/v1/notifications
```json
{
  "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
  "span_id": "00f067aa0ba902b7",
  "metadata": {
    "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
    "span_id": "00f067aa0ba902b7"
  },
  "created": 2,
  "duplicates": 0,
  "notifications": [
    {
      "id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
      "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
      "channel": "sms",
      "priority": "high",
      "status": "pending"
    }
  ]
}
```

GET /api/v1/notifications
```json
{
  "data": [
    {
      "id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
      "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
      "channel": "sms",
      "priority": "high",
      "status": "sent"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

GET /api/v1/notifications/{notificationId}
```json
{
  "id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
  "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
  "channel": "sms",
  "priority": "high",
  "status": "sent"
}
```

POST /api/v1/notifications/{notificationId}/cancel
```json
{
  "message": "Notification cancelled.",
  "notification": {
    "id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
    "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
    "channel": "sms",
    "priority": "high",
    "status": "cancelled"
  }
}
```

GET /api/v1/batches/{batchId}
```json
{
  "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
  "status": "pending",
  "total_count": 2,
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
  "span_id": "00f067aa0ba902b7",
  "metadata": {
    "source": "api"
  },
  "notifications": [
    {
      "id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
      "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
      "channel": "sms",
      "priority": "high",
      "status": "pending"
    }
  ]
}
```

POST /api/v1/batches/{batchId}/cancel
```json
{
  "message": "Batch cancelled.",
  "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
  "cancelled_count": 2
}
```

GET /api/v1/metrics
```json
{
  "queues": {
    "high": 0,
    "normal": 2,
    "low": 0,
    "dead": 0
  },
  "status_counts": {
    "pending": 2,
    "sent": 10,
    "failed": 1
  },
  "dead_letter_count": 1,
  "circuit_breaker": {
    "sms": "closed",
    "email": "closed",
    "push": "open"
  },
  "avg_latency_seconds": 1.25
}
```

GET /api/v1/metrics/prometheus
```
# HELP notification_queue_depth Number of jobs waiting in each queue.
# TYPE notification_queue_depth gauge
notification_queue_depth{queue="high"} 0
notification_queue_depth{queue="normal"} 2
notification_queue_depth{queue="low"} 0
notification_queue_depth{queue="dead"} 0
# HELP notification_status_total Count of notifications by status.
# TYPE notification_status_total gauge
notification_status_total{status="pending"} 2
notification_status_total{status="sent"} 10
notification_status_total{status="failed"} 1
# HELP notification_dead_letter_total Total dead-letter notifications.
# TYPE notification_dead_letter_total gauge
notification_dead_letter_total 1
# HELP notification_circuit_breaker_state Circuit breaker state by channel (1=open, 0=closed).
# TYPE notification_circuit_breaker_state gauge
notification_circuit_breaker_state{channel="sms"} 0
notification_circuit_breaker_state{channel="email"} 0
notification_circuit_breaker_state{channel="push"} 1
# HELP notification_avg_latency_seconds Average time between created and sent.
# TYPE notification_avg_latency_seconds gauge
notification_avg_latency_seconds 1.25
```

GET /api/v1/health
```json
{
  "status": "ok",
  "dependencies": {
    "database": "ok",
    "redis": "ok"
  }
}
```

GET /api/v1/dead-letter
```json
{
  "data": [
    {
      "id": "8b2f0c3a-0e1a-4b4e-9f18-3d9f2d2f3b1a",
      "notification_id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
      "channel": "sms",
      "error_type": "permanent",
      "error_code": "http_400"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

GET /api/v1/dead-letter/{deadLetterId}
```json
{
  "id": "8b2f0c3a-0e1a-4b4e-9f18-3d9f2d2f3b1a",
  "notification_id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b",
  "channel": "sms",
  "error_type": "permanent",
  "error_code": "http_400"
}
```

POST /api/v1/dead-letter/{deadLetterId}/requeue
```json
{
  "message": "Dead-letter notification requeued.",
  "notification_id": "2e2b6b71-5f20-4d2f-90fb-bcaea8d6ed7f"
}
```

POST /api/v1/dead-letter/requeue
```json
{
  "message": "Dead-letter requeue completed.",
  "requested": 100,
  "requeued": 95,
  "skipped": 5
}
```

POST /api/v1/dead-letter/requeue (no eligible items)
```json
{
  "message": "Dead-letter requeue completed.",
  "requested": 100,
  "requeued": 0,
  "skipped": 100
}
```

POST /api/v1/templates
```json
{
  "id": "6c1b29f0-8b2d-4d5c-9db0-6b4d5a3c2f1e",
  "name": "welcome",
  "channel": "sms",
  "content": "Hello {{name}}"
}
```

GET /api/v1/templates
```json
{
  "data": [
    {
      "id": "6c1b29f0-8b2d-4d5c-9db0-6b4d5a3c2f1e",
      "name": "welcome",
      "channel": "sms",
      "content": "Hello {{name}}"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

GET /api/v1/templates/{templateId}
```json
{
  "id": "6c1b29f0-8b2d-4d5c-9db0-6b4d5a3c2f1e",
  "name": "welcome",
  "channel": "sms",
  "content": "Hello {{name}}"
}
```

PATCH /api/v1/templates/{templateId}
```json
{
  "id": "6c1b29f0-8b2d-4d5c-9db0-6b4d5a3c2f1e",
  "name": "welcome",
  "channel": "sms",
  "content": "Hello {{name}}, updated!"
}
```

DELETE /api/v1/templates/{templateId}
```json
{
  "message": "Template deleted."
}
```

POST /api/v1/templates/preview
```json
{
  "template_id": "6c1b29f0-8b2d-4d5c-9db0-6b4d5a3c2f1e",
  "content": "Hello Ada",
  "variables": {
    "name": "Ada"
  }
}
```

### Error Responses
401 Unauthorized (missing/invalid `X-Api-Key`)
```json
{
  "message": "Unauthorized"
}
```

409 Conflict (cancel when not pending/scheduled)
```json
{
  "message": "Notification cannot be cancelled.",
  "status": "sent"
}
```

409 Conflict (idempotency)
```json
{
  "message": "Notification idempotency key already exists.",
  "idempotency_key": "notif-123",
  "notification_id": "0b6d8b4b-4f1c-4e7f-a4b1-2c3d4e5f6a7b"
}
```

409 Conflict (batch idempotency)
```json
{
  "message": "Batch idempotency key already exists.",
  "batch_id": "3a7f7c5e-9bd1-4c4d-96b7-7c1d7b7b9f4a",
  "duplicates": 2
}
```

422 Validation Error (example)
```json
{
  "message": "The notifications field is required.",
  "errors": {
    "notifications": [
      "The notifications field is required."
    ]
  }
}
```

## What Each Endpoint Does (Plain English)
- `POST /api/v1/notifications`  
  Create one or many notifications in a single request. Supports priority and templates.

- `GET /api/v1/notifications`  
  List notifications with filters (status, channel, date range) and pagination.

- `GET /api/v1/notifications/{id}`  
  Check the delivery status of a single notification.

- `POST /api/v1/notifications/{id}/cancel`  
  Cancel a notification that hasn’t been sent yet.

- `GET /api/v1/batches/{batchId}`  
  View the status of a group of notifications created together.

- `POST /api/v1/batches/{batchId}/cancel`  
  Cancel all pending notifications in a batch.

- `GET /api/v1/metrics`  
  Operational metrics: queue sizes, success/failure counts, average latency, dead‑letter count, and circuit‑breaker state.

- `GET /api/v1/metrics/prometheus`  
  Prometheus‑formatted metrics for scraping.

- `GET /api/v1/health`  
  Simple health check for database and Redis connectivity.

- `GET /api/v1/dead-letter`  
  View notifications that permanently failed (for manual review or reprocessing).

- `GET /api/v1/dead-letter/{deadLetterId}`  
  View details of a single dead‑letter record.

- `POST /api/v1/dead-letter/{deadLetterId}/requeue`  
  Requeue a dead‑letter record as a new notification for delivery retry.

- `POST /api/v1/dead-letter/requeue`  
  Requeue multiple dead‑letter records back into the queue.

- `POST /api/v1/templates`  
  Create a reusable message template with placeholders.

- `GET /api/v1/templates`  
  List all templates.

- `GET /api/v1/templates/{templateId}`  
  Get a single template by ID.

- `PATCH /api/v1/templates/{templateId}`  
  Update a template’s content or default variables.

- `DELETE /api/v1/templates/{templateId}`  
  Delete a template.

- `POST /api/v1/templates/preview`  
  Render a template with variables to preview the final message.

## Simple Example Flow (Non-Technical)
1) Marketing creates a “Flash Sale” template once.  
2) The system sends millions of personalized messages using that template.  
3) If a provider has temporary issues, the system retries automatically.  
4) If a message still fails, it goes into the dead‑letter list for review.  
5) Operations monitors system health and message throughput with the metrics endpoint.

## WebSocket Updates
Notification status changes are broadcast on the `notifications` channel with event name `notification.status.updated`.

Example payload:
```json
{"notificationId":"uuid","status":"sent"}
```

For local dev, set `BROADCAST_CONNECTION=log` or configure a broadcaster (e.g., Pusher/Reverb) and use Laravel Echo.

To run Reverb locally:
```bash
docker compose exec app php artisan reverb:start
```
Reverb listens on `ws://localhost:8080` by default.

## Distributed Tracing
The API accepts and returns the `traceparent` header (W3C Trace Context). Trace IDs are stored on notifications and forwarded to the provider.

Example:
```bash
curl -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
  http://localhost:8000/api/v1/health
```

## CI/CD
GitHub Actions workflow runs tests on every push/PR using PHP 8.4 and SQLite. See `.github/workflows/ci.yml`.

## OpenAPI
See `docs/openapi.yaml`.

## Swagger UI
Open `http://localhost:8000/swagger` to browse the API documentation.

## Testing
```bash
php artisan test
```

## Stress Test
Generate a burst of notifications (direct DB + queue):
```bash
php artisan notifications:stress --count=1000 --batch=200 --channel=sms --priority=high
```

Use API mode (requires API key + app running):
```bash
php artisan notifications:stress --mode=api --api-key=local-api-key --count=500 --batch=100
```

### Integration Tests
Integration tests require Redis + queue workers. Enable them with:
```bash
INTEGRATION_TESTS=1 php artisan test --testsuite=Integration
```

### Run All Tests (Single Command)
```bash
composer test-all
```

## Architecture Overview
- **NotificationBatch** groups notifications for batch tracking and idempotency.
- **Notification** stores channel-specific payload, status, and delivery metadata.
- **NotificationAttempt** records delivery attempts and provider responses.
- **SendNotificationJob** processes notifications with per-channel throttling and retry logic.
- **DispatchScheduledNotifications** command sends due scheduled notifications every minute.

## Notes
- The external provider is simulated via webhook.site.
- Set `NOTIFICATION_PROVIDER_WEBHOOK_URL` before sending notifications.
