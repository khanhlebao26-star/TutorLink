# D06-02 — Environment readiness

- **Result:** PASS for isolated test runner and PostgreSQL setup; local HTTP runtime is not ready.
- **Captured:** 2026-11-04T19:55:17+07:00 (Asia/Ho_Chi_Minh)
- **Source:** branch `be-d06`, HEAD `d157a8eeae662c01d6bd6b031ea53c26a5b05688`. Dependency lock hashes are carried from [D06-01 source manifest](../2026-11-04-193718/source-manifest.json).

## Isolated test environment

- Compose project: `tutorlink-d06-test`; configs: `compose.testing.yml` plus [D06 test override](../../../../../compose.d06.testing.yml) and [HTTP runtime override](../../../../../compose.d06.runtime.testing.yml). Both override files keep the evidence mount at `outputs/be-d06:/evidence`; `.env.docker` is mounted read-only and all DB/app test settings are overridden by Compose.
- PostgreSQL: `postgres:16-alpine`, server PostgreSQL 16.15 on x86_64-pc-linux-musl, compiled by gcc (Alpine 15.2.0) 15.2.0, 64-bit; container is healthy, on Compose's internal-only network, has no host-published port, and stores its data on `tmpfs`.
- Runner: `tutorlink-d06-backend-test:local`, PHP 8.3.36, Laravel 13.35.0. Image build succeeded with `composer install` from the locked `composer.lock`; platform requirements passed. `php artisan --version` bootstrapped successfully.
- Guard: `php scripts/test-console.php check` returned SAFE for `pgsql`, host `test-db`, DB `tutorlink_test`, user `tutorlink_test_runner`, server `172.30.0.2`; database CONNECT/CREATE and `public` schema CREATE privileges were true. Guard and DB privilege checks were read-only.
- Side-effect controls: `APP_ENV=testing`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `BROADCAST_CONNECTION=null`. No Slack/email provider or public database port is configured in this Compose profile.

The first image build used the base Compose file before the D06 override existed, so it also refreshed the shared `tutorlink-h01-backend-test:local` tag. The subsequent D06-specific build uses its own tag; both builds emitted the same image config digest. The pre-existing H01 test container is stopped and was not restarted or modified.

## Host and application runtime findings

- Host PHP is 8.3.6. Both `composer check-platform-reqs --lock` and installed-vendor `composer check-platform-reqs` passed.
- Host test console guard returned exit 1 with its safe-target refusal because the host's configured DB is not an allowed isolated test target. No DB mutation was attempted.
- Host `php artisan --version` fails during bootstrap because `Laravel\Telescope\Telescope` is absent from the host vendor tree. The isolated runner installed Telescope from the lock and boots.
- The existing TutorLink local backend and DB containers are stopped; broadcast, outbox and Slack workers are restarting. Therefore local HTTP smoke is not ready and is not claimed as PASS. Status is recorded in `local-runtime-status.log`.

## Planned smoke target

After D06-10–12 fixtures and contract checks are ready, run the guarded migration/fixture steps in project `tutorlink-d06-test`; repeat `test-console.php check` before any destructive command. Start the app only with the three Compose files above: `docker compose -f compose.testing.yml -f compose.d06.testing.yml -f compose.d06.runtime.testing.yml -p tutorlink-d06-test up -d backend-test`. The HTTP target will bind only to `127.0.0.1:18006` and use the same internal PostgreSQL test DB. Check readiness and run D06-13 synthetic-actor smoke cases there. Do not use the host runtime until its bootstrap and DB identity are repaired.

No migration, reset, seeding, test suite, or HTTP smoke was run for D06-02. The test DB currently contains only its initial empty database/schema.

## Evidence

See `environment-manifest.json`, `runner-guard.log`, `runner-platform.log`, `runner-artisan-version.log`, `test-db-privileges.log`, `test-db-version.log`, `host-guard-failure.log`, `host-bootstrap-failure.log`, and `local-runtime-status.log`.
