# Runtime container rebuild and recovery

- **Date:** 2026-11-05 (Asia/Ho_Chi_Minh).
- **Project:** Docker Compose project `tutorlink`, files `docker-compose.yml` and `compose.workers.yml`.
- **Scope:** `backend`, `broadcast-worker`, `outbox-worker`, `scheduler`, and `slack-worker`. Database, frontend, Mailpit, pgAdmin, and application data volumes were not rebuilt or reset.

## Failure and cause

Before rebuild, backend exited with code 1 and four workers were restarting with exit code 1. All five recent logs reported `Class "Laravel\\Telescope\\Telescope" not found` while loading `backend/bootstrap/providers.php`.

The current Dockerfile successfully builds `laravel/telescope` from `composer.lock` (Telescope v5.27.0). However, Compose mounts the named volume `backend_vendor` at `/var/www/html/vendor`. That existing volume masked the rebuilt image's dependency directory and did not contain Telescope. Rebuilding the images alone reproduced the same error, confirming that stale mounted dependencies—not the new image build—were the remaining cause.

## Recovery

1. Rebuilt `backend`, `broadcast-worker`, `outbox-worker`, `scheduler`, and `slack-worker` using:

   ```sh
   docker compose -f docker-compose.yml -f compose.workers.yml -p tutorlink build backend broadcast-worker outbox-worker scheduler slack-worker
   ```

2. Ran `composer install --no-interaction --prefer-dist --no-progress --no-scripts` through a one-off Compose container with the existing `backend_vendor` volume mounted. Composer synchronized that generated dependency volume from the current lock file; the database and storage volumes were untouched.
3. Recreated the backend and waited for its configured healthcheck, then recreated the four workers after the backend became healthy.

## Verification

- Backend image ID after rebuild: `sha256:4d1699a8438ffa33a07ad5f435570f97d0502300e53d6618d5f1bf12f1de13bb`.
- `class_exists("Laravel\\Telescope\\Telescope")` through the mounted volume: **present**.
- Backend: **running, healthy, restart count 0**.
- `GET /sanctum/csrf-cookie` against the local backend: HTTP **204**.
- Broadcast, Outbox, Scheduler, and Slack workers: **running, restart count 0**; no Telescope/bootstrap errors in the post-recovery log window.
- Other pre-existing containers (`backend-test`, stopped `test-db`) were reported as Compose orphans because they belong to a different configuration set; they were not removed.

The services were observed healthy/running for at least 40 seconds after recreation. No production or external service was contacted.
