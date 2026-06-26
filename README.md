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
GitHub Actions workflow runs:\n+- Laravel Pint (format check)\n+- Migrations against Postgres service\n+- PHPUnit tests\n+
