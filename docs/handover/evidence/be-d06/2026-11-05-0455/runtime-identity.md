# BE-D06 — Runtime identity

Captured on 05/11/2026 (Asia/Ho_Chi_Minh) from the isolated `tutorlink-d06-smoke` Compose project. The tested D06 source revision is `cb881f02bfde6ddab340eb2c0316781677d73117`, based on main `fb54886617524e43727705f7f6ee86bed4135ef9`.

| Component | Identity and state |
| --- | --- |
| Backend Compose service | `backend-test`, running, host binding `127.0.0.1:18006` only |
| Backend image | `sha256:955e6d57a4899bb2abb43ccad63755a8f37439c3a66f627497345c19e7aeecf8` |
| PHP | 8.3.36 CLI |
| Laravel | 13.35.0 |
| PostgreSQL image | `postgres:16-alpine`; server 16.15, healthy |
| Test database | `tutorlink_test`, user `tutorlink_test_runner`, host `test-db`; 38 migrations, latest batch 1 |
| Runtime configuration | `backend/d06-smoke.env`, mounted read-only over the base Compose development env; synthetic test values only |

The `testing` database network is internal and has no published PostgreSQL port. The backend is attached to a separate bridge only to permit the host-side HTTP smoke client; its published port is loopback-only. The runtime does not connect to the app stack, production database, Slack, or email.

The runner's `php scripts/test-console.php check` returned `SAFE: test database identity verified` with configured host/database/user matching the connected PostgreSQL identity. It did not run a migration or reset during this check. The 38 migrations and fixture seeding were run earlier against this same disposable Compose test database as part of the smoke setup.

The HTTP runtime uses the built testing image, not a source bind mount. SHA-256 values for all nine modified runtime PHP files (the eight API controllers and `bootstrap/app.php`) matched between the container and the workspace. This links the recorded HTTP behavior to the D06 source being handed over. Image build and migration records are in [the smoke build log](../2026-11-05-0443/smoke-runtime-build-final.log) and [migration log](../2026-11-05-0443/smoke-migrate-final.log); response evidence is in [the HTTP transcript](../2026-11-05-0443/http-smoke.json).

The test runner artifact was built separately with the testing Dockerfile. Composer lock SHA-256: `56b6094f45759aa5667f6c16378c59862eca00a6d7fdd5b1d28931395d09bb0b`.
