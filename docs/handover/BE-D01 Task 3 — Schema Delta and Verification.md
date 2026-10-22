# BE-D01 Task 3 — Schema Delta and Verification

- **Date:** 2026-10-22
- **Branch:** `codex/2w-be-d01`
- **Commit:** Not created; changes remain uncommitted for review.

**Scope:** Admin Assignment, Notification Outbox, Task Submission versioning/idempotency, PostgreSQL verification, and controlled deployment to live.

## Status and ownership

| Schema delta | Owner in Issue #36 | Implementer | Test status |
|---|---|---|---|
| Admin Assignment | Hoàng | Codex, at user direction | PASS on PostgreSQL test DB; deployed live |
| Notification Outbox | Khanh | Codex, at user direction | PASS on PostgreSQL test DB; deployed live |
| Task Submission | Dũng | Codex, at user direction | PASS on PostgreSQL test DB; deployed live |

After the user's explicit approval, the three Task 3 migrations were applied individually to live `tutorlink` on 2026-10-22 at 08:24 UTC (15:24 Asia/Ho_Chi_Minh). They were recorded in batches 4, 5, and 6. The unrelated older migrations were not run. The preceding status-alignment migration remains present on live. Three older migrations remain Pending: public tutor slug, two-factor columns, and passkeys.

## Migrations

### Admin Assignment

Migration: `backend/database/migrations/2026_10_22_073014_add_admin_assignment_columns_to_tutor_profiles_and_reports.php`

- Added nullable `assigned_admin_id`, `assigned_by`, and `assigned_at` plus `assignment_version NOT NULL DEFAULT 0` to `tutor_profiles` and `reports`.
- Both user references use `ON DELETE SET NULL`; existing `reviewed_by` and `handled_by` remain unchanged.
- Added non-negative version checks and queue indexes on `(status, assigned_admin_id, submitted_at)` and `(status, assigned_admin_id, created_at)`.
- The database FK validates that an assignee exists; role, active status, and permission checks remain application responsibilities.

### Notification Outbox

Migration: `backend/database/migrations/2026_10_22_073015_create_notification_outbox_and_add_event_key_to_notifications.php`

- Added `notifications.event_key NOT NULL` and unique `(user_id, type, event_key)`.
- Existing notifications without a source event key receive the marker `legacy:<notification UUID>`. The row and payload are preserved; the marker does not claim to reconstruct the original business event key.
- Added `notification_outbox` with UUID identity, recipient/resource/event fields, JSONB payload, retry/availability/processed/failed/replay/resolution timestamps, safe error code, and non-negative attempt count.
- Added unique `(user_id, type, event_key)` and worker partial index on `(available_at, id)` using the Database Design predicate: unprocessed, unresolved, and not failed unless a later replay was requested.
- Worker, retry/backoff, replay authorization, and retention jobs are not part of this schema migration.

### Task Submission

Migration: `backend/database/migrations/2026_10_22_073016_add_submission_versioning_and_idempotency.php`

- Added `tasks.version NOT NULL DEFAULT 1` and nullable `current_submission_id`.
- Added non-null `submission_version`, UUID `idempotency_key`, and `CHAR(64) request_hash` to `task_submissions`.
- Replaced unique `(task_id, customer_id)` with unique `(task_id, customer_id, submission_version)` and `(task_id, customer_id, idempotency_key)`.
- Added hash/version checks, descending `(task_id, submission_version)` index, and composite current-submission foreign keys. A current pointer must match its Task and assigned Customer.
- The migration stops before schema changes if legacy submission rows exist. Their original idempotency key and request hash cannot be recovered safely; no synthetic replay evidence is generated.
- API replay, stale-write responses, authorization, file rules, and Task state updates remain runtime work.

Legacy guard evidence: a separate disposable PostgreSQL database was seeded with one valid legacy Task Submission. Running this migration raised the expected `RuntimeException`; the row and content remained intact, none of the new Task/Submission columns were added, and the migration was not recorded as complete. This is a deliberate safe stop, not a backfill policy.

## Verification evidence

- Source snapshot: `/tmp/tutorlink_pre_status_test_20261022.dump` (137 KB), created before status alignment and Task 3.
- Targeted rehearsal DB: `tutorlink_status_test_20261022`. Assignment, Outbox, and Submission migrations all ran. PostgreSQL rejected nonexistent users, duplicate event keys, negative assignment/attempt/submission/task versions, malformed request hashes, duplicate submission versions, and a current pointer to another Task/Customer. Valid rows and two submission versions were accepted in transactions and rolled back. The legacy notification marker and payload were verified.
- Full-chain rehearsal DB: `tutorlink_task3_chain_test_20261022`, restored from the source snapshot. All seven pending migrations ran in order: four older migrations (public tutor slug, status alignment, two-factor columns, and passkeys) plus all three Task 3 migrations. `migrate:status` showed all migrations Ran. A synthetic legacy notification fixture was retained and backfilled, its payload was verified, and the fixture was then removed.
- Legacy Submission guard DB: `tutorlink_task3_legacy_guard_test_20261022`. The expected preflight failure preserved the synthetic legacy row and left the schema unchanged.
- Focused feature tests: `Task3SchemaDeltaTest` passed **3 tests, 22 assertions** on PostgreSQL (`tutorlink_task3_phpunit_test_20261022`) and **3 tests, 19 assertions** on SQLite `:memory:`. PostgreSQL constraint failures run inside savepoints so later assertions can continue in the same test transaction.
- Live deployment verification: `migrate:status` shows all three Task 3 migrations Ran. PostgreSQL introspection confirms the expected columns, foreign keys, checks, unique constraints, queue indexes, and partial worker index. There are 18 tutor profiles (7 `draft`, 11 `submitted`); all 18 retain null assignment fields and `assignment_version = 0`. Live currently has 0 reports, notifications, outbox rows, tasks, and task submissions. Because live notifications were empty, no legacy notification rows required backfill.
- Fresh live backup: `/tmp/tutorlink_pre_task3_live_20261022.dump`, 138 KB, custom-format archive; `pg_restore -l` succeeded. SHA-256: `1a509dd486a3a8f764602dbc7ec65ae105ff0d7dfcb21867eb08d9c4de399fba`.
- PHP syntax checks passed for the three migration files and feature test. `git diff --check` passed.
- Full backend suite on SQLite: **99 passed, 8 skipped, 3 failed**. The failures were two age-boundary assertions comparing a date-only value with SQLite's date-time representation, plus `ExampleTest` requiring a `sessions` table without refreshing/migrating the database. The focused Task 3 tests pass; these three failures are outside the Task 3 schema changes.

## Post-deployment state and remaining runtime scope

- Live `tutorlink` now has the Task 3 schema and no Task 3 test fixture writes. A fresh backup was taken and validated immediately before deployment; the older 137 KB source snapshot above predates the status-alignment migration.
- No commit has been created; the branch changes are uncommitted and available for review.
- Schema work does not implement Assignment APIs, Notification workers, or Submission APIs. Those require their own runtime work and verification.
- Submission legacy guard: live had zero submission rows when deployed. On another environment, if legacy submissions exist, approve a reviewed history/idempotency migration policy; the migration safely stops and must not be bypassed with invented metadata.
- Outbox retry/backoff thresholds, replay authorization, alerting, and retention remain operational decisions in `UNRESOLVED_DECISIONS_2026-10-20.md`; this schema does not choose defaults for them.
