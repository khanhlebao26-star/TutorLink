# TutorLink deployment preparation — November 19, 2026

Target: **Next.js frontend on Vercel; Laravel backend on a VPS using Docker Compose**. No VPS, domain or production SMTP account was supplied. This delivery prepares and verifies local code; it does not certify a production deployment. GitHub Issue/PR work was stopped at the user's request; no Git push or PR was performed for this delivery.

## Quality and coverage

The starting commit was `b3814fd`. The local frontend baseline measured **40.60% lines (1,412/3,477)**. The final measurement covers the same source glob, including the new runtime code, with no new source exclusions: **71.03% lines (2,492/3,508)**. Branch and function coverage remain separate metrics; the 70% gate is a line-coverage gate, not a claim that every metric exceeds 70%.

The final frontend suite passed **145 tests** across 33 files, with lint, typecheck and production build passing.

The guarded PostgreSQL backend runner completed 857 tests / 7,585 assertions, including migration preflight and concurrency groups. Backend coverage was **90.39% lines (8,821/9,759)**. The combined checker also verifies backend statement coverage independently rather than averaging frontend and backend.

New frontend tests cover marketplace search, public tutor/listing reads, owner listing creation/edit/publish, availability validation, favorites, verification/reset, account/password/learning-profile flows, quiz submission, task reads, report creation/retry/evidence and error recovery. Existing messaging success/retry/session tests remain in the suite. CI now enforces frontend lines >70% and joins uploaded backend/frontend artifacts for the separate coverage gate. CI records source SHA/runtime and uploads full reports. **Local checks passed; a new cloud CI run was not triggered.**

## Production browsing and performance

Both frontend variants were production builds against the same isolated PostgreSQL dataset of 5,000 listings. Chrome ran three fresh contexts at 390×844 and 1440×1000, navigating homepage → marketplace → public tutor. Raw reports/screenshots live under `outputs/deploy-readiness/`; the reproducible runner is [production-journey.mjs](../../frontend/scripts/production-journey.mjs).

The baseline exposed a real guest-session race: `/me` returned 401 and dispatched a session-reset event while public reads were pending. The generic hook aborted the public result and left the tutor skeleton visible. Public reads now explicitly opt out of session-scoped clearing; private reads still clear on logout and permission failure. All six baseline tutor samples failed readiness within five seconds; all six after samples displayed the tutor.

Other changes: responsive hero-image `sizes`; explicit marketplace prefetch; deferred loading of the below-fold featured-tutor section. A homepage-scoped provider preserves one candidate snapshot for the service section and the later tutor section, so deferral does not cause another candidate request. Failed loads can retry; snapshots are not shared across users or server requests.

| Local browser metric | Before | After |
| --- | ---: | ---: |
| Hero encoded image bytes, both viewports | 50,826 | 44,256 |
| Homepage ready median, mobile | 815 ms | 860 ms |
| Homepage ready median, desktop | 884 ms | 850 ms |
| Marketplace ready median, mobile | 757 ms | 898 ms |
| Marketplace ready median, desktop | 1,036 ms | 703 ms |
| Tutor readiness across both viewports | 0/6 | 6/6 |

The hero image used 6,570 fewer encoded bytes (about 12.9%). Timings are mixed; do not claim a general page-speed percentage improvement. `ready_ms` includes Playwright's network-idle wait, and three samples per viewport are descriptive rather than a field p95. Screenshots do not prove every application flow.

The isolated read workload offered 20 RPS for 30 seconds against the same data and request mix, including 120 searches per run. Search p95 was **51 ms before and 86 ms after**, with all 120 searches successful in both runs. The backend query implementation was unchanged in this phase; these results do not demonstrate a search-speed improvement. The generator, Docker application, browser and build/test tools share one machine; results are not staging capacity. One intermediate after run failed its authentication smoke after changing the benchmark frontend origin; it was excluded and rerun after restoring both allowed benchmark/browser stateful origins. No invalid run is counted as a pass.

## Resource monitoring and errors

[dashboard.py](../../docker/monitoring/dashboard.py) provides a loopback-only resource dashboard using Python's standard library and Docker CLI. It shows per-container CPU, RAM, cumulative network RX/TX and the collector host's filesystem usage. On macOS, container limits describe the Docker VM, while disk usage describes the Mac filesystem. On a Linux VPS, it describes that host. It does not claim to measure physical host CPU from container CPU totals.

Defaults: 80% CPU per container, 80% RAM per container and 80% disk, with a 60-second notification cooldown. CPU may exceed 100% across multiple cores. For the delivery proof only, the collector ran with disk threshold 0% and sent a real measured disk event to its local HTTP receiver; `received-alerts.jsonl` confirms delivery. This is a local webhook demonstration, not delivery to Slack or email.

A controlled outage stopped only the disposable benchmark database. A listing request returned HTTP 500 with a request ID; the application stderr recorded the server exception, and the JSON response did not expose SQL details. The test database was restarted. Added frontend `not-found`, `error`, `global-error` and structured `onRequestError` logging. Error UI avoids showing raw exception messages and offers retry/home recovery; instrumentation records route/method/digest without request cookies, query values or message content.

Run the local dashboard:

```sh
python3 docker/monitoring/dashboard.py --prefix tutorlink- --port 9070
```

Open `http://127.0.0.1:9070`. Keep it on loopback; on the VPS, use an SSH tunnel instead of publishing it publicly:

```sh
ssh -L 9070:127.0.0.1:9070 your-user@your-vps
```

## Vercel and VPS configuration

Use custom domains such as `app.your-domain.com` for Vercel and `api.your-domain.com` for the VPS. This implementation uses Sanctum cookies and CSRF; use matching parent-domain cookie configuration. Arbitrary Vercel preview domains and an unrelated API domain need a separately designed auth/proxy setup and are not accepted by this template.

1. On Vercel, select the repository's `frontend` root, Next.js framework, Node.js 22, `npm ci`, and `npm run build`. Leave output handling to Next.js/Vercel.
2. Copy the values from [frontend/.env.production.example](../../frontend/.env.production.example) into Vercel's Production environment. Set the browser API origin and the public Reverb key/`wss://` origin before building. Never expose Laravel APP_KEY, Reverb secret, SMTP or database credentials as NEXT_PUBLIC variables.
3. On the VPS, create `.env.production` from the [backend deployment example](../../.env.production.example). Set a unique release tag, random Laravel APP_KEY, database password, Reverb secret and real SMTP credentials. Configure `SESSION_DOMAIN=.your-domain.com`, frontend/API HTTPS origins, Sanctum host and Reverb origin allow-list consistently. Keep database sessions and queues.
4. Check the environment without printing secret values:

   ```sh
   python3 docker/production/validate_env.py .env.production
   docker compose --env-file .env.production -f compose.production.yml config --quiet
   ```

5. Build images and start the database, apply migrations, then start the runtime. Take a database backup before migrations on an existing deployment.

   ```sh
   docker compose --env-file .env.production -f compose.production.yml build app proxy
   docker compose --env-file .env.production -f compose.production.yml up -d db
   docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan migrate --force --no-interaction
   docker compose --env-file .env.production -f compose.production.yml up -d
   ```

6. Configure DNS/TLS and verify `/up`, CSRF, login/logout, cookie domain, protected API denials, public tutor data, WebSocket authorization/reconnect, and real email delivery. Confirm `emails` and `broadcasts` workers consume their matching queues and that failed jobs/queue lag are monitored. Rebuild Vercel when NEXT_PUBLIC values change.
7. Use the existing [backup/restore/rollback runbook](BE-K05.6-backup-restore-rollback-runbook.md) for recovery. Verify restore on a separate database before accepting the release.

See [Next.js on Vercel](https://vercel.com/docs/frameworks/full-stack/nextjs) and the [artifact download action](https://github.com/actions/download-artifact) for the platform configuration used here.

## Saved evidence

- [Quality and source coverage](evidence/deploy-readiness-2026-11-19/quality.json)
- [Browser before/after summary](evidence/deploy-readiness-2026-11-19/browser-summary.json)
- [Search runs](evidence/deploy-readiness-2026-11-19/search-runs.json)
- [Real-browser deferred loading and 404 checks](evidence/deploy-readiness-2026-11-19/browser-extra.json)
- [500 response/log proof](evidence/deploy-readiness-2026-11-19/error-proof.json)
- [Received local alert](evidence/deploy-readiness-2026-11-19/received-alert.json)
- [Resource dashboard](evidence/deploy-readiness-2026-11-19/resource-dashboard.png) · [404 page](evidence/deploy-readiness-2026-11-19/frontend-404.png)
- [Base commit, build IDs and changed-source hashes](evidence/deploy-readiness-2026-11-19/source-state.json)

The last browser check also confirmed the deferred tutor cards appear on scroll with exactly one candidate API request across both homepage sections. The missing-route response was 404 with a working home link. The deployment validator, Compose interpolation using synthetic settings, and CI YAML parsing passed. Ten Python tests cover threshold evaluation, environment validation and the independent coverage gate.

## Verification commands

```sh
# Dedicated test database, never the normal local database
 docker compose -p tutorlink-deploy-tests -f compose.testing.yml run --build --rm backend-test php scripts/test-suite.php --coverage

# Frontend
cd frontend
npm run lint
npm run typecheck
npm run test:coverage
npm run build
cd ..
python3 frontend/scripts/check-codebase-coverage.py frontend/coverage/coverage-summary.json backend/build/reports/clover.xml
python3 frontend/scripts/test-coverage-gate.py
python3 -m unittest discover -s docker/monitoring -p 'test_*.py'
python3 -m unittest discover -s docker/production -p 'test_*.py'
```

When running the backend container to write reports to the host, mount `backend/build/reports` to `/var/www/html/build/reports`. The raw full-suite reports are generated evidence, not committed source. The local test/benchmark services are disposable and distinct from the developer's database. No new dependencies or business API/schema changes were introduced.
