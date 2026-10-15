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

Before seeding, set `LOCAL_ADMIN_EMAIL` and `LOCAL_ADMIN_PASSWORD` in
`backend/.env.docker`. Keep the password private; do not commit it or paste it
into an issue, pull request, or log.

Generate an application key and put the printed value in
`backend/.env.docker`:

```powershell
docker compose run --rm backend php artisan key:generate --show
```

Create the schema and start the services:

```powershell
docker compose run --rm backend php artisan migrate --force
docker compose run --rm backend php artisan db:seed --force
docker compose up -d
docker compose ps
```

Open:

- Frontend: `http://localhost:3000`
- Backend health endpoint: `http://localhost:8000/up`
- Local email inbox: `http://localhost:8025` (Mailpit)
- pgAdmin database UI: `http://127.0.0.1:5050`
- PostgreSQL from the host: `127.0.0.1:5433`

Sign in to the local pgAdmin UI with `admin@tutorlink.dev` and
`pgadmin_dev_password`. Open the preconfigured **TutorLink Docker** server and
enter the local database password (`POSTGRES_PASSWORD` in `docker-compose.yml`).
Browse **Databases > tutorlink > Schemas > public > Tables**, then right-click
a table and choose **View/Edit Data > All Rows**. pgAdmin uses `db:5432` within
Docker to reach the same database published on host port 5433. Port 5433 itself
is a PostgreSQL connection endpoint and cannot display a browser page.

To add pgAdmin to an already running stack, run `docker compose up -d --no-deps pgadmin`.
The UI is bound to the local machine, and its settings persist in `pgadmin_data`.

Use `localhost` for both frontend and backend in the browser. `0.0.0.0` is
the server's listen address; visiting it uses a different origin and breaks
the localhost cookie/CSRF flow. The frontend redirects that host to
`http://localhost:3000`, preserving the path and query string.

Inside the backend container, PostgreSQL is addressed as `db:5432`, not
`127.0.0.1:5433`.

## Useful commands

```powershell
docker compose logs -f backend
docker compose logs -f frontend
docker compose logs -f mailpit
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

## Local email and Admin data

The Docker stack sends mail through Mailpit (`mailpit:1025`). Registration sends
an email verification message whose link opens the frontend verification flow.
Password reset messages open
`http://localhost:3000/auth/reset-password`; reset tokens expire according to
the backend configuration and repeated requests within 60 seconds return JSON
`429` with a `Retry-After` header.

After setting the private local Admin password, run:

```powershell
docker compose run --rm backend php artisan db:seed --force
```

`DatabaseSeeder` is safe to run repeatedly: it creates or updates the single
configured local Admin and grants the complete Admin permission set. The Admin
credentials are intentionally not documented here.

## Auth/File/Profile smoke checks

The current API routes are:

- Auth: `/api/v1/auth/register`, `/login`, `/logout`, `/me`, email verification,
  and password reset.
- Catalog: `/api/v1/catalog/categories` and
  `/api/v1/catalog/specializations`.
- Files: upload, complete, link, metadata, download, and delete under
  `/api/v1/files`.
- Tutor profile: `/api/v1/tutor/profile`, `/submit`, `/avatar`, and
  `/verification-documents`.

Business file uploads require a verified email. Uploaded files start with
`scan_status=pending`; only completed files (`scan_status=clean`) can be
attached or downloaded. Tutor profiles move from `draft` to `submitted`, and
submitted profiles are locked by policy.

Tutor onboarding uses this sequence:

1. Read `GET /api/v1/catalog/specializations`.
2. Create or update a draft profile with `specialization_id`.
3. Upload a file with purpose `avatar` or `verification_document`.
4. Complete the file with `POST /api/v1/files/{file}/complete`.
5. Attach an avatar with `PUT /api/v1/tutor/profile/avatar` and payload
   `{ "file_id": 123 }`.
6. Attach a verification document with
   `POST /api/v1/tutor/profile/verification-documents` and payload
   `{ "file_id": 123, "document_type": "teaching_certificate" }`.

After changing `backend/.env.docker`, recreate the backend so Laravel loads the
new environment file:

```powershell
docker compose up -d --force-recreate backend mailpit
docker compose exec backend php artisan config:clear
```

## HTTP 500 and CORS troubleshooting

A running container does not guarantee a working Laravel API. The backend
healthcheck requests `/sanctum/csrf-cookie` so a missing encryption key also
appears as an unhealthy backend in Docker/Portainer.

If the browser reports missing CORS headers, inspect the backend response
and Laravel log before changing the allowed origins:

```powershell
curl.exe -i -H "Origin: http://localhost:3000" -H "Accept: application/json" http://localhost:8000/sanctum/csrf-cookie
docker compose exec backend tail -n 80 storage/logs/laravel.log
docker compose exec backend php artisan migrate:status
```

- `No application encryption key has been specified`: generate the key using
  the clean-checkout command above, save it as `APP_KEY` in `.env.docker`,
  then recreate the backend and clear its config. The container mounts `.env`
  read-only, so use `key:generate --show` and edit the host file.
- `Migration table not found` or missing relations: run `php artisan migrate
  --force` in the backend container, then seed after setting the local Admin
  credentials.
- Origin `http://0.0.0.0:3000`: reload through `http://localhost:3000` and log
  in again. Allowing that origin alone does not fix cookies shared with
  `localhost:8000`.

Expected results: CSRF returns `204` with
`Access-Control-Allow-Origin: http://localhost:3000`; the Admin API returns
`401` before login and `200` after an authorized Admin logs in.
