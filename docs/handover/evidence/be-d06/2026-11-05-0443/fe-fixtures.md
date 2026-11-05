# BE-D06 synthetic fixtures for FE

This fixture set reuses `Database\\Seeders\\BeD04FixtureSeeder`, which is idempotent by synthetic email/title/key and creates the actors, four Program states, Fee history, Progress pagination ties, Announcement visibility/file cases, Sessions, and Task/Submission states needed by D06.

## Safe entry point

Run from the repository root. `compose.testing.yml` supplies a PostgreSQL 16 test database on an internal network with a `tmpfs` data directory; the Laravel `TestDatabaseGuard` checks both configured and connected identity before reset/seed. Never replace this runner's environment with an application or production database.

```sh
docker compose -f compose.testing.yml run --build --rm -T backend-test sh -lc 'php scripts/test-console.php migrate:fresh && php scripts/test-console.php d04:seed && php scripts/be-d06-fixtures.php' > docs/handover/evidence/be-d06/2026-11-05-0443/fixture-manifest.json
```

The generated JSON maps stable aliases to IDs for that test database instance. IDs are run-specific; FE clients should resolve aliases from the manifest instead of hardcoding numeric IDs. Running the command again resets the isolated test database, then recreates the same logical aliases. It does not seed the running TutorLink application.

## Alias groups

- Actors: owner, other Tutor, active member, outsider, admin, inactive account, revoked member, and late member.
- Programs: active, paused, completed, and cancelled.
- Announcements: two same-time published rows, an owner-only draft, and a publication from before membership revocation.
- Fees: zero amount and current period; the seeder also creates the full monthly history from 2022-01 through 2026-11.
- Progress: two latest rows with tied timestamps plus the final row of a 55-row pagination set.
- Tasks: one each in todo, in-progress, submitted, completed, and cancelled states, with pending/accepted current Submissions.
- Files: valid, wrong owner, wrong purpose, pending scan, and rejected scan attachment fixtures. File bodies are synthetic PDFs in the isolated fixture storage.
- Sessions: upcoming, completed, and cancelled schedule rows.

The seeder's fixture count is 8 actors, 4 Programs, 59 Fees, 55 Progress rows, 4 Announcements, 5 Files, 3 Sessions, 5 Tasks and 2 Submissions. There are no real user records, passwords or production secrets in the manifest.

## Behavior and request examples

The request/response transcript index is in [`examples/index.md`](examples/index.md). Examples must be produced from actual HTTP requests in D06-13 and retain only safe headers/body fields. No example in this folder should be treated as an HTTP transcript until its case row links to a generated transcript and records its source image/commit.
