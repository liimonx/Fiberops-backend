# Fiberops Backend (Laravel)

Laravel API backend scaffold for Fiberops.

## Stack
- **Laravel** (PHP)
- **PostgreSQL** (default database)
- **Laravel Sanctum** (API token auth)
- **Docker Compose** (local dev: app + postgres + redis)

## Requirements
- PHP 8.3+ and Composer (for running locally without Docker), or
- Docker Desktop + Docker Compose (recommended)

## Local development (Docker)

Services started by `docker compose up`:

| Service | Purpose |
|---------|---------|
| `app` | Laravel API on port **8000** |
| `db` | PostgreSQL |
| `redis` | Pub/sub for real-time map updates |
| `ws` | WebSocket gateway on port **8080** (`ws://localhost:8080/ws`) |
| `scheduler` | Runs `mikrotik:sync` every 15 seconds |

1) Create a local env file:

```bash
cp .env.example .env
php artisan key:generate
```

2) Start services:

```bash
docker compose up -d --build
```

3) Run migrations:

```bash
docker compose exec app php artisan migrate
```

API will be available at `http://localhost:8000`.

## Local development (no Docker)
1) Create `.env` and set DB values (see `.env.example`), then:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

## API endpoints
Base path: `/api/v1`

### Auth
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout` (requires Bearer token)
- `GET /api/v1/me` (requires Bearer token)

### Domain (all require Bearer token)
- Assets: `GET/POST /api/v1/assets`
- Customers: `GET/POST /api/v1/customers`, `GET/PATCH /api/v1/customers/:id`
- Incidents: `GET/POST /api/v1/incidents`, `GET/PATCH /api/v1/incidents/:id`
- Work orders: `GET/POST /api/v1/work-orders`, `GET/PATCH /api/v1/work-orders/:id`
- Planning: `GET/POST /api/v1/planning/proposals`, `GET/PATCH /api/v1/planning/proposals/:id`
- Stats: `GET /api/v1/stats/usage`
- Reports: `GET /api/v1/reports/*`, `POST /api/v1/reports/generate`
- Settings: `GET/PATCH /api/v1/settings/*`

### WebSocket
- `ws://localhost:8080/ws` (via `docker compose` `ws` service; fans out Redis `org:{id}:network` events)

### Mikrotik (RouterOS)
Configure under Settings → Integrations in the frontend, or via API:

- `PATCH /api/v1/settings/integrations/mikrotik` — host, port, credentials, `monitoredInterface`
- `POST /api/v1/settings/integrations/mikrotik/test` — verify connectivity

The scheduler polls PPPoE sessions, interface bandwidth, and netwatch targets, then publishes map updates over Redis → WebSocket.

**Router API user:** read-only access to PPP active sessions, interfaces, and netwatch.

**Env vars:** see `.env.example` (`MIKROTIK_SYNC_INTERVAL`, `MIKROTIK_INTERFACE_POLL_INTERVAL`, `MIKROTIK_MOCK`).

Router credentials are encrypted at rest (Laravel `Crypt`) in organization settings.

### Example usage (curl)
Register:

```bash
curl -sS -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123"}'
```

Login:

```bash
TOKEN=$(curl -sS -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}' | jq -r .token)
echo "$TOKEN"
```

Me:

```bash
curl -sS http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer $TOKEN"
```

Logout:

```bash
curl -sS -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

## CI
GitHub Actions workflow runs:
- Laravel Pint (format check)
- Migrations against Postgres service
- PHPUnit tests

## Make targets
- `make up` — start Docker services (migrations run on start)
- `make migrate` — run migrations
- `make seed` — seed database
- `make test` — run PHPUnit

## Next work

See the frontend repo backlog: [FiberOps/docs/backend/next-tasks.md](https://github.com/liimonx/FiberOps/blob/main/docs/backend/next-tasks.md) (or `../FiberOps/docs/backend/next-tasks.md` in a multi-root workspace).
