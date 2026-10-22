# BE-D01 Task 2 — Schema Delta and Migration

**Date:** 2026-10-22  
**Scope:** schema inspection, design delta, migration, PostgreSQL verification, deployment, backup, and ERD update.

## Baseline and design comparison

The pgAdmin target was PostgreSQL 16.15, database `tutorlink`, with 39 public base tables. These are actual tables, not rows in Laravel's `migrations` table. The Database Design Draw.io contains 43 target tables; four are not yet present at runtime: `tutor_profile_revisions`, `listing_moderation_holds`, `notification_outbox`, and `idempotency_receipts`.

Catalog values are stored as text with PostgreSQL `CHECK` constraints rather than separate enum catalog tables. The baseline had no `programs.customer_id`. Before deployment, the affected lifecycle tables (`programs`, `program_invitations`, `program_members`, `program_sessions`, `lesson_sessions`, `task_submissions`) were all empty. The ownership evidence and status preflight found no ambiguous or unmapped rows.

## Schema delta implemented

Migration: `backend/database/migrations/2026_10_22_000000_align_program_schema_with_domain_design.php`

- Add `programs.customer_id` as an FK to `users.id`; enforce NOT NULL on PostgreSQL after backfill.
- Backfill only when legacy Customer membership and/or invitation evidence identifies exactly one Customer user. Validate the user's role. Abort on missing or conflicting evidence; do not guess.
- Align lifecycle values: `programs.pending → draft`, `program_invitations.active → accepted`, `program_sessions.planned → scheduled`, and `task_submissions.reviewed → accepted`.
- Replace the PostgreSQL lifecycle checks for Programs, Invitations, Program Members, Program Sessions, Lesson Sessions, and Task Submissions.
- Add partial unique indexes for one pending Invitation per Program and one Customer membership per Program, including historical membership rows.
- Preserve existing stable identity constraints, including Program/version and Program/user uniqueness.

Preflight blocks on ambiguous Customer ownership, duplicate pending Invitations, unsupported statuses (including Program `rejected`/`expired`, `lesson_sessions.in_progress`/`no_show`), and unsupported member roles such as `observer`. `Program pending → draft` is used for the not-yet-activated legacy state. The migration's `down()` refuses automatic reversal because lifecycle conversions can be lossy.

## Backup and deployment

Created a full custom-format PostgreSQL backup before deployment:

`backend/storage/app/private/backups/tutorlink_pre_task2_20261022.dump`

The file is mode `0600`, 139,877 bytes; `pg_restore -l` read its archive directory successfully (348 TOC entries). This backup is kept in the ignored local private storage directory and is not committed.

Applied only the Task 2 migration to `tutorlink` with the explicit migration path. Four older migrations remain Pending and were not run: public tutor slug, user/tutor status alignment, user two-factor columns, and passkeys.

## PostgreSQL verification

- Full PostgreSQL Laravel suite on isolated test DB: **107 passed, 551 assertions**.
- Legacy fixture migration on isolated test DB preserved row counts: one row each in `programs`, `program_members`, `program_invitations`, `program_sessions`, `tasks`, and `task_submissions`. States mapped to `draft`, `accepted`, `scheduled`, and `accepted`; `customer_id` was backfilled from unique Customer evidence.
- Live preflight found the six affected tables empty; no business rows were changed during the deployment.
- Live post-deploy checks confirmed `programs.customer_id` is `bigint NOT NULL`, all seven lifecycle/role checks and both partial unique indexes exist, and the migration is recorded in batch 2.
- Catalog seeder idempotency was covered in the isolated PostgreSQL suite. No live seed was run.

## ERD

Updated the source [TutorLink Database Design.drawio](https://drive.google.com/file/d/1SBHxrpU7pQcvDdb76PwFRlc6FD9ABM-q/view) in place. The ERD already had the 43-table target and `programs.customer_id` relationship; the update corrects the stale 37-table overview title and adds a dated deployment entry distinguishing the 43-table target from the 39-table runtime. The entry records the actual FK, lifecycle checks, unique indexes, empty-table preflight, backup, and the four remaining absent tables. A local copy of the updated source is retained at `backend/storage/app/private/backups/TutorLink Database Design.drawio`.

## Remaining scope

This migration covers foundational Program ownership and lifecycle constraints. It does not implement the separately assigned tutor assignment, notification outbox, submission schema work, or the four absent target tables. Those remain separate schema deltas.
