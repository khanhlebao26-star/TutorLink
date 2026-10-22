# BE-D01 Task 5 — Final Database Verification

- **Issue:** #36
- **Branch:** `codex/2w-be-d01`
- **Status:** Partially accepted; see criterion statuses below
- **Environment:** Isolated PostgreSQL 16 test databases in Docker Compose
- **Checked:** 2026-10-22 (Asia/Ho_Chi_Minh)

## 1. Objective

Verify the full branch migration chain, repeatable seeds, preservation of the available legacy backup, PostgreSQL lifecycle/constraint behavior, and the ERD against the schema actually migrated in the test environment.

## 2. Preflight and safety

- Branch is `codex/2w-be-d01`. The worktree already contained uncommitted Task 3 documentation, a Task 4 handover and the Task 4 migration; these were preserved.
- Docker Compose `backend` and PostgreSQL 16 services were healthy. Compose defaults to database `tutorlink`.
- The automatic approval review rejected even a read-only command against the real `tutorlink` database because this task only authorized a verified isolated test database. No command connected to or changed `tutorlink`; its current migration state was therefore **not independently rechecked for Task 5**. The prior Task 4 handover records `public_slug` as Pending on live, but that is historical evidence, not a fresh status check.
- The documented archive `/tmp/tutorlink-live-pre-be-d01-task4-20261022.dump` was present (148 KB); SHA-256 matched the Task 4 record: `b61926ec5f89c847aaaf2113225da5de1afcbcceff5dd4f645b01d33481c535f`. `pg_restore --list` in the Postgres container confirmed a custom-format PostgreSQL 16.15 archive with 369 TOC entries.
- Created isolated databases `tutorlink_be_d01_task5_fresh_20261022` and `tutorlink_be_d01_task5_legacy_20261022`. Laravel's resolved database name was checked for both before use. The backup was restored only to the legacy test database.

## 3. Fresh PostgreSQL migration

- **Database:** `tutorlink_be_d01_task5_fresh_20261022`
- **Command:** `docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=tutorlink_be_d01_task5_fresh_20261022 backend php artisan migrate --force`
- **Result:** PASS. All 23 migrations on the branch ran successfully; `migrate:status` reported 23 `Ran`, and the database recorded 23 migration rows with none unrecorded.
- This includes the `public_slug`, status alignment, 2FA, Passkeys, Task 2–4 schema changes and `2026_10_22_080000_enforce_utc_timestamps_and_vnd_currency`.

## 4. Seed repeatability

- **Database:** fresh test database only.
- Ran `php artisan db:seed --force` twice with `APP_ENV=testing` and disposable `task5-admin@example.test` local-admin credentials. No real account or credential was used.

| Check | Run 1 | Run 2 | Result |
|---|---:|---:|---|
| Categories | 4 | 4 | PASS |
| Subcategories | 4 | 4 | PASS |
| Specializations | 5 | 5 | PASS |
| Local admin | 1 | 1 | PASS |

All five category/subcategory/specialization slug paths were identical after both runs. The local admin remained one active Admin. No duplicate key or unexpected row growth occurred.

## 5. Legacy restore and preservation

- **Database:** `tutorlink_be_d01_task5_legacy_20261022`
- **Source:** validated Task 4 custom-format backup above; restore used `pg_restore --no-owner --role=tutorlink`.
- **Pre-migration row baseline:** users 51; tutor_profiles 18; files 24; programs 0; program_invitations 0; program_members 0; tasks 0; task_submissions 0; notifications 0.
- Recorded ordered MD5 fingerprints of selected stable business fields before and after migration. The values matched:

| Table | Before | After | Fingerprint |
|---|---:|---:|---|
| users | 51 | 51 | `6fef1a10367ce8cbee4423aec602221f` |
| tutor_profiles | 18 | 18 | `6b52d2b1de53fdc1e0c12803726d0b22` |
| files | 24 | 24 | `680f809e3d1c3052f792fc4809dc22e1` |
| programs | 0 | 0 | empty |
| task_submissions | 0 | 0 | empty |
| notifications | 0 | 0 | empty |

- Applied the four migrations that were Pending in the restored snapshot: public slug, two-factor columns, Passkeys and Task 4 UTC/VND conventions. All 23 migrations then showed `Ran`. All 18 Tutor Profiles had a non-null public slug after backfill; checked task/file ownership joins had zero orphans.
- **Result for the restored snapshot:** PASS — inspected rows and selected content were preserved through the final migration chain. No legacy Program, Invitation, Member, Task, Submission or Notification rows existed in this particular snapshot, so it does not exercise their populated-data transformations.
- **Prior fixture evidence, not rerun as part of this pass:** Task 2 handover records successful Program/Invitation/Member/Session/Task/Submission migration on a populated small fixture. Task 3 handover records notification payload preservation and a Submission guard fixture that stopped before schema changes while preserving its row. These support the focused migration behavior but are not a populated full-chain Task 5 fixture.
- **Submission successful-upgrade status:** BLOCKED when historical Submission rows exist. The migration intentionally refuses to invent idempotency keys/request hashes; no approved conversion policy exists. The Task 3 safe-stop test is PASS, but it is not proof that a populated Submission database can be upgraded.

## 6. Issue #36 acceptance criteria

| Criterion | Evidence | Status |
|---|---|---|
| No legacy data loss | Restored snapshot counts and selected content fingerprints match; existing focused Task 2/3 fixture evidence is linked above | PASS for the restored snapshot; **BLOCKED** for populated all-migration-chain fixtures not present in the backup |
| `completed` supported | PostgreSQL accepted `programs.status = 'completed'` and persisted it in a valid fixture row | PASS |
| UNIQUE/CHECK behavior verified | Transaction-scoped PostgreSQL cases below raised the expected SQLSTATE classes; all fixtures rolled back | PASS |
| Unresolved mappings marked Blocked | Explicit mapping/blocker register in Section 8 | PASS |

### PostgreSQL behavior cases

Executed in a transaction against the fresh test database and rolled the transaction back. Positive and negative cases used disposable User, Tutor Profile, Program, Invitation, membership, Task, Submission and Notification rows.

| Case | Observed result |
|---|---|
| Program `completed` | Accepted and persisted |
| Program `invalid_state` | Rejected: `check_violation` |
| Second pending Invitation for same Program | Rejected: `unique_violation` |
| Second Customer membership for same Program | Rejected: `unique_violation` |
| Duplicate Submission `(task, customer, version)` | Rejected: `unique_violation` |
| Duplicate Submission `(task, customer, idempotency_key)` | Rejected: `unique_violation` |
| Duplicate Notification `(user, type, event_key)` | Rejected: `unique_violation` |
| Negative `tutor_profiles.assignment_version` | Rejected: `check_violation` |
| Negative Fee amount | Rejected: `check_violation` |
| Fee currency `USD` | Rejected: `check_violation` |
| Availability weekday `-1` and `7` | Both rejected: `check_violation` |

These checks exercise PostgreSQL behavior, not application lifecycle authorization such as whether a Program is eligible to complete.

## 7. ERD

- **Updated artifact:** [`TutorLink Database Design.drawio`](../Architecture%20%26%20Technical%20Design/TutorLink%20Database%20Design.drawio), retained with the documentation; source Drive link is listed in [`Other source files.md`](../Architecture%20%26%20Technical%20Design/Other%20source%20files.md).
- Updated the ERD change log with the Task 5 rehearsal evidence and deployment boundary. Existing pages show the target `programs.customer_id` relationship, Admin Assignment, `notification_outbox`, Submission version/idempotency and Task current-submission pointer.
- **Schema comparison:** design target 43 tables; fresh migrated PostgreSQL schema has 40 application tables. `notification_outbox` exists. `tutor_profile_revisions`, `listing_moderation_holds` and `idempotency_receipts` remain target-only without migrations. Their absence is labeled in the ERD and is not presented as deployed.
- `programs.customer_id`, assignment fields, outbox relation and Submission constraints were present in the fresh schema; migration and behavior checks confirmed the relevant keys and constraints.
- **Result:** PASS for target/runtime distinction and the verified 40-table test schema. The live database was not inspected or changed in this pass; the local ERD is not a write-back to the source Drive file.

## 8. Known blockers and unresolved mapping

| Item | Status | Impact | Resolution required |
|---|---|---|---|
| Legacy Submission rows without idempotency metadata | **Blocked** for successful upgrade | Migration halts if rows exist; successful upgrade cannot be claimed for such databases | Approve and document a data conversion policy, or retain the safe-stop requirement and resolve rows before migration |
| Populated legacy fixtures across the final full chain | **Blocked** | The available Task 4 backup has no Program/Invitation/Submission/Notification rows; earlier focused fixtures did not run the entire final chain | Produce reviewed, synthetic compatibility fixtures and rerun full-chain comparison if this coverage is required before PR |
| `public_slug` on live | **Pending / current status unverified** | Prior Task 4 handover says the migration remains Pending; it backfills existing Profiles | Separately review its data effects and obtain deployment approval; no live operation was performed here |
| `tutor_profile_revisions`, `listing_moderation_holds`, `idempotency_receipts` | **Blocked / not migrated** | Target ERD has 43 tables; current fresh runtime has 40 | Implement and verify migrations in their owning work items before claiming complete target-schema deployment |
| Outbox worker/retry/replay/retention runtime | **Blocked for operational rollout** | Schema and BR-25 policy exist; this task verifies database structure, not worker delivery/operations | Implement and separately verify worker, alerting, replay authorization/audit and retention behavior |
| Generic D08 idempotency receipt runtime | **Blocked for generic runtime** | BR-23 policy is decided, but `idempotency_receipts` has no migration/runtime table | Implement its schema/runtime in the owning work item; Submission retains its specialized tested constraint policy |

## 9. Test summary

- **Fresh migration:** PASS, 23/23.
- **Fresh seed repeatability:** PASS, two identical runs.
- **Legacy backup restore and selected data preservation:** PASS for the actual restored snapshot.
- **Populated legacy Program fixture in the final full chain:** BLOCKED; prior focused Task 2 evidence exists.
- **Legacy Submission guard:** PASS in the earlier Task 3 disposable fixture; successful upgrade with rows remains BLOCKED by policy.
- **Completed/UNIQUE/CHECK PostgreSQL behavior:** PASS for the listed cases.
- **ERD/test schema comparison:** PASS for 40 actual versus 43 target tables.
- **Live database status:** NOT VERIFIED in this pass; no live connection or write occurred.
- No application regression suite was run as part of this database acceptance request.

## 10. Final conclusion

**Task 5: PARTIAL PASS / BLOCKED for full acceptance.** Fresh migration, repeated seed, restored-backup preservation, PostgreSQL lifecycle/constraint behavior and the test-schema ERD comparison passed. Full acceptance still needs a populated compatibility fixture across the final migration chain if required by review, an agreed successful-upgrade policy for legacy Submission rows, and separate implementation work for the three target tables and outbox/generic idempotency runtime. Live migration status was not rechecked and no production database operation was attempted.

**Issue #36:** This report is ready for review. The current evidence supports a review/PR for the completed schema work with the blockers explicit; it does not support claiming every target schema/runtime item is complete or proceeding to live rollout. Before deployment, prepare a separate plan with backup validation, reviewed migration scope, rollback/recovery steps and explicit approval.
