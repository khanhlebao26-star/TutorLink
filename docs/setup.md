# TutorLink local setup

## Requirements

- Docker Desktop with Compose v2.
- Git.

The Docker environment supplies PHP 8.3, PostgreSQL 16, Node.js 22, and the
Laravel `pdo_pgsql` extension. Host PHP, Composer, Node, and PostgreSQL are not
required for the Docker workflow.

## Clean checkout

```powershell
git clone <repository-url>
cd tutorlink-src
Copy-Item backend\.env.docker.example backend\.env.docker
docker compose build
```

Generate an application key and put the printed value in
`backend/.env.docker`:

```powershell
docker compose run --rm backend php artisan key:generate --show
```

Create the schema and start the services:

```powershell
docker compose run --rm backend php artisan migrate --force
docker compose up -d
docker compose ps
```

Open:

- Frontend: `http://localhost:3000`
- Backend health endpoint: `http://localhost:8000/up`
- PostgreSQL from the host: `127.0.0.1:5433`

Inside the backend container, PostgreSQL is addressed as `db:5432`, not
`127.0.0.1:5433`.

## Useful commands

```powershell
docker compose logs -f backend
docker compose logs -f frontend
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan test
docker compose down
```

Do not run `docker compose down -v` unless you intentionally want to delete the
development PostgreSQL volume.

## Sanctum smoke check

1. Start the stack with `docker compose up -d`.
2. Request `GET http://localhost:8000/sanctum/csrf-cookie` from the frontend
   origin with credentials enabled.
3. Confirm the response sets the CSRF/session cookies.
4. Confirm the frontend client sends `withCredentials: true` and the XSRF
   header for later requests.

The current repository contains the platform configuration and contract; Auth,
File, Catalog, and Tutor Profile MVP endpoints are included in the current
feature branch. Listing remains contract-only until its marketplace feature is
implemented.

## Auth/File/Profile smoke checks

The current API routes are:

- Auth: `/api/v1/auth/register`, `/login`, `/logout`, `/me`, email verification,
  and password reset.
- Catalog: `/api/v1/catalog/categories` and
  `/api/v1/catalog/specializations`.
- Files: upload, complete, link, metadata, download, and delete under
  `/api/v1/files`.
- Tutor profile: `/api/v1/tutor/profile` and `/submit`.

Business file uploads require a verified email. Uploaded files start with
`scan_status=pending`; only completed files (`scan_status=clean`) can be
attached or downloaded. Tutor profiles move from `draft` to `submitted`, and
submitted profiles are locked by policy.

After changing `backend/.env.docker`, recreate the backend so Laravel loads the
new environment file:

```powershell
docker compose up -d --force-recreate backend
docker compose exec backend php artisan config:clear
```
