# BE-D01 Task 4 — Database Conventions Review

- Issue: #36
- Branch: `codex/2w-be-d01`
- Database: PostgreSQL rehearsal `tutorlink_task3_chain_test_20261022`
- Status: **Done on rehearsal and applied to live after explicit authorization**
- Checked: 22/10/2026

## Objective

Verify the foreign keys, uniqueness rules, indexes, VND integer money, UTC/weekday conventions, and the separation between HTTP sessions and learning sessions against the Task 4 checklist and the target Database Design.

## Verification

| Area | Expected | Actual on rehearsal | Result |
|---|---|---|---|
| Foreign keys | Business ownership references match the design; historical Program/Submission rows are protected; reviewer/assignee references may be nulled | `programs.customer_id`, `programs.owner_tutor_profile_id`, `program_invitations.program_id`, `task_submissions.task_id` and `notification_outbox.user_id` exist. Program/Submission references use `RESTRICT`; assigned Admin references on Reports/Tutor Profiles use `SET NULL`. `tasks.current_submission_id` has both composite FKs to the same Task and assigned Customer, using `RESTRICT`. These were verified from PostgreSQL catalog definitions. There are 18 Tutor Profiles but all `assigned_admin_id` values are NULL; Reports, Programs, Invitations, Submissions and Outbox have no rows. | PASS — schema verified; limited row-level exercise |
| UNIQUE | Pending Invitation, Customer membership, Submission version/idempotency and notification event uniqueness | Partial unique indexes exist for one pending Invitation per Program and one Customer membership per Program. Submission version and idempotency keys are unique by Task/Customer; Notification and Outbox are unique by user/type/event key. These were verified from PostgreSQL index definitions; those tables have no rows to exercise duplicate rejection. | PASS — schema verified; row-level exercise unavailable |
| Indexes | Marketplace, inbox, task, submission history, notification, audit and report queries have useful indexes | Verified `service_listings_marketplace_index`, `program_invitation_inbox_index`, `tasks_program_id_due_at_status_index`, descending Submission history index, Notification inbox index, `audit_logs_resource_created_index` and report queue/status indexes. No exact duplicate among the reviewed indexes was found. | PASS |
| VND integer | Money stored as non-negative integer VND and currency restricted to VND | `agreed_fee_minor`, `price_from_minor` and `amount_minor` are `bigint`; their non-negative CHECKs exist. Added `currency = 'VND'` CHECKs on Programs, Listings and Fee Records. All three tables are empty, so there were no existing money values to validate; the CHECK definitions were verified in PostgreSQL. | PASS — schema verified; no money rows |
| UTC / weekday | Instant columns use `timestamptz`; weekday is 0–6; Program timezone is retained | `program_sessions.planned_starts_at/ends_at` and `tasks.due_at` are `timestamptz`; `programs.timezone` defaults to `Asia/Ho_Chi_Minh`; availability uses local `time` columns and has a 0–6 weekday CHECK. Converted the legacy timestamp-without-time-zone instant columns listed below to `timestamptz`, interpreting their stored values as UTC. Post-check found no remaining `timestamp without time zone` columns. PostgreSQL/app timezone is UTC. | PASS |
| Session conventions | HTTP auth session is distinct from scheduled and performed lesson data | `sessions` has Laravel session ID, optional user ID, payload and integer `last_activity`; `user_id` is indexed but has no FK, as documented. `program_sessions` stores planned/scheduled Program sessions and timezone-aware instants; `lesson_sessions` stores execution details and joins one-to-one to `program_sessions`. | PASS |
| Report / blockers | Evidence and blockers are recorded; live migration scope is explicit | Task 4 and its two authorized schema prerequisites are `Ran` on live; the unrelated public-slug data migration remains Pending. Catalog and aggregate checks returned zero money/weekday violations. | PASS |

## Schema delta applied to rehearsal

Added [`2026_10_22_080000_enforce_utc_timestamps_and_vnd_currency.php`](../../backend/database/migrations/2026_10_22_080000_enforce_utc_timestamps_and_vnd_currency.php).

The migration:

- Adds CHECK constraints limiting `programs.currency`, `service_listings.currency` and `fee_records.currency` to `VND`.
- Converts these legacy instant columns to `timestamptz` using `AT TIME ZONE 'UTC'`: `failed_jobs.failed_at`; `passkeys.last_used_at/created_at/updated_at`; `password_reset_tokens.created_at`; `personal_access_tokens.last_used_at/expires_at/created_at/updated_at`; and `users.email_verified_at/created_at/updated_at/two_factor_confirmed_at`.
- Keeps availability rule start/end times as local `time without time zone`; these are recurring local wall-clock times, not instants.

Conversion basis: `config('app.timezone')` is `UTC`, and PostgreSQL reported session timezone `UTC` before and after the migration. The `AT TIME ZONE 'UTC'` conversion preserves the stored wall-clock values as UTC instants.

## Evidence

- `php artisan migrate:status` against the named test database: all Task 2/3 migrations were `Ran`; the new migration is batch 4 and `Ran`.
- `php artisan migrate --force` with `DB_CONNECTION=pgsql` and `DB_DATABASE=tutorlink_task3_chain_test_20261022`: completed successfully.
- Read-only PostgreSQL catalog queries verified constraints, indexes, and timestamp types before/after migration.
- Aggregate checks after migration: negative/foreign-currency money rows = 0 for Programs, Listings and Fee Records; invalid weekdays = 0; remaining timestamp-without-time-zone columns = 0.
- `sessions.user_id` is not an FK; the composite Task/Submission FKs and required unique indexes are present.

## Actual rehearsal data inspected

Row counts from `tutorlink_task3_chain_test_20261022` after the migration:

| Table | Rows |
|---|---:|
| `users` | 51 |
| `tutor_profiles` | 18 |
| `files` | 24 |
| `file_links` | 0 |
| `programs` | 0 |
| `program_invitations` | 0 |
| `program_members` | 0 |
| `service_listings` | 0 |
| `fee_records` | 0 |
| `tasks` | 0 |
| `task_submissions` | 0 |
| `notification_outbox` | 0 |
| `notifications` | 0 |
| `messages` | 0 |
| `conversations` | 0 |
| `sessions` | 0 |
| `program_sessions` | 0 |
| `lesson_sessions` | 0 |
| `availability_rules` | 0 |
| `reports` | 0 |
| `audit_logs` | 3 |
| `passkeys` | 0 |
| `personal_access_tokens` | 0 |

The 51 users are 3 active Admins, 25 active Customers and 23 active Tutors. Below are the first 10 rows with only IDs, role/status and timestamp fields; names, email addresses and credentials are intentionally omitted.

| User ID | Role | Status | `created_at` UTC | `email_verified_at` UTC | `two_factor_confirmed_at` UTC |
|---:|---|---|---|---|---|
| 1 | admin | active | 2026-10-12 22:48:22+00 | 2026-10-12 22:48:22+00 | NULL |
| 2 | customer | active | 2026-10-12 23:07:35+00 | NULL | NULL |
| 3 | admin | active | 2026-10-12 23:41:13+00 | NULL | NULL |
| 4 | customer | active | 2026-10-12 23:41:28+00 | NULL | NULL |
| 5 | customer | active | 2026-10-12 23:42:08+00 | 2026-10-12 23:42:09+00 | NULL |
| 6 | tutor | active | 2026-10-12 23:42:12+00 | 2026-10-12 23:42:12+00 | NULL |
| 7 | customer | active | 2026-10-12 23:46:23+00 | 2026-10-12 23:46:25+00 | NULL |
| 8 | tutor | active | 2026-10-12 23:46:31+00 | 2026-10-12 23:46:32+00 | NULL |
| 9 | customer | active | 2026-10-12 23:47:54+00 | 2026-10-12 23:47:56+00 | NULL |
| 10 | tutor | active | 2026-10-12 23:48:01+00 | 2026-10-12 23:48:02+00 | NULL |

Tutor Profile assignment data: 7 profiles are `draft` and 11 are `submitted`; all 18 have `assigned_admin_id = NULL`, `assigned_by = NULL`, `assigned_at = NULL` and `assignment_version = 0`. Thus the assignee FK is present and nullable, but no assigned-user relationship is exercised by current data.

Actual Audit rows (only action/resource/time fields):

| ID | Action | Resource type | `created_at` UTC |
|---:|---|---|---|
| 1 | `tutor_profile_changes_requested` | `tutor_profile` | 2026-10-12 23:58:44+00 |
| 2 | `tutor_profile_changes_requested` | `tutor_profile` | 2026-10-13 09:19:53+00 |
| 3 | `tutor_profile_changes_requested` | `tutor_profile` | 2026-10-13 09:25:29+00 |

**Interpretation:** FK/unique/money checks above are proven by PostgreSQL catalog definitions and successful migration, not by populated Program, Submission, Fee or Invitation rows. This rehearsal database cannot demonstrate actual duplicate rejection or real money-row preservation because those tables contain no rows. That is a data-coverage limitation, not a schema failure.

## Decisions and blockers

- Money remains integer **VND đồng** despite `_minor` suffixes; the database now enforces the VND currency convention.
- UTC conversion is based on the application's configured UTC timezone. Availability rules remain local times under their timezone field; `Program.timezone` remains the authoritative scheduling timezone.
- No rehearsal row currently exercises the three currency columns, so the VND constraint was structurally verified but no existing currency values were available to inspect.
- Scope for this Task 4 pass is schema conventions and preservation of current data. No business fixtures were added; the empty business tables above are their current state, not a pending data-population task.
- The migration contains no `INSERT`, `UPDATE`, `DELETE` or `TRUNCATE`. It adds CHECK constraints and converts legacy UTC timestamp columns to `timestamptz`; existing timestamp wall values are interpreted as UTC instants. After migration, the inspected row counts include 51 users, 18 Tutor Profiles, 24 Files and 3 Audit rows.
- **No Task 4 blocker on rehearsal or live.** The live migration was applied after the two authorized schema-only prerequisites; the public-slug data migration remains pending.

## Conclusion

**Task 4 schema review is complete on rehearsal and the migration is applied to live `tutorlink`.** It was run after only the explicitly approved schema prerequisites. No business data was seeded or removed. The unrelated public-slug backfill remains pending.

## Live rollout preflight — 22/10/2026

The live database `tutorlink` was checked read-only before migration. It is in UTC; Programs, Listings and Fee Records had 0 rows, with no negative amounts. The Task 4 migration was Pending, along with three earlier migrations: `2026_10_13_162057_add_public_slug_to_tutor_profiles_table`, `2026_10_20_101959_add_two_factor_columns_to_users_table` and `2026_10_20_102000_create_passkeys_table`.

Task 4 expected `users.two_factor_confirmed_at` and the `passkeys` table, which did not yet exist live. After explicit user authorization, only the two schema prerequisites and Task 4 were applied using individual `--path` runs. The unrelated public-slug migration was not run and remains Pending; it backfills `public_slug` for existing Tutor Profiles.

Before migration, a compressed full database backup was created and its archive directory validated with `pg_restore --list` (369 TOC entries): `/tmp/tutorlink-live-pre-be-d01-task4-20261022.dump`. SHA-256: `b61926ec5f89c847aaaf2113225da5de1afcbcceff5dd4f645b01d33481c535f`.

After migration, the 2FA prerequisite is batch 7, Passkeys batch 8 and Task 4 batch 9, all `Ran`. The public-slug migration remains `Pending`. PostgreSQL reports UTC and no remaining `timestamp without time zone` columns. The three VND CHECKs exist; currency/negative-money/weekday violation counts are all zero. Current row counts are 51 users, 18 Tutor Profiles, 24 Files and 3 Audit rows; all previously empty business tables remain empty. No application rows were seeded or removed.
