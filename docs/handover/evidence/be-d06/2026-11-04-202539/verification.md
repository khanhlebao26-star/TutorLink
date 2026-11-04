# D06-04 — Verification record

- Finalized: `2026-11-04T20:31:29+07:00` (Asia/Ho_Chi_Minh).
- Commit baseline: `d157a8eeae662c01d6bd6b031ea53c26a5b05688`.
- Source diff (two controllers and one test, `git diff --binary HEAD -- <paths>`): SHA-256 `c4570bc206702ffe8506e8acca0e6889e45210b69e59f8cc0fe8bebf5e22ac54`, 6346 bytes. The worktree remains dirty; D06-01/02 manifests record the pre-existing changes.
- Runner image after source rebuild: `tutorlink-d06-backend-test:local`, ID `sha256:7ad474b9647e45aae3522a307b714ea8820ac1b03ec84a0820de3935e2845130`; PHP 8.3.36; PostgreSQL test identity `tutorlink_test` / `tutorlink_test_runner` at `test-db` (D06-02 environment manifest).
- `graphify query "ProgramController InvitationController InvitationAcceptController Program Invitation request resource action tests D06-04"` — exit 0.
- `graphify query "ProgramController create update transition request resource action ProgramApiTest ProgramLifecycleApiTest InvitationController InvitationAcceptController request resource action tests" --budget 5000` — exit 0.
- After rebuilding the runner (exit 0), the lead ran `php scripts/test-console.php check` in that image immediately before the focused test; guard exit 0 (`guard-before-focused-rerun.txt`).
- Focused test command from repository root (exit 0):

```sh
docker compose -f compose.testing.yml -f compose.d06.testing.yml -p tutorlink-d06-test run --rm --no-deps backend-test php artisan test --filter='returns 403 for a suspended Tutor before changing Program lifecycle state'
```

- Corrected focused result: **PASS**, 1 test / 5 assertions, 0.70s (`focused-test-rerun.txt`). It verifies `403 account_inactive`, unchanged Program state/version, and no audit/outbox writes.
- The focused test's `RefreshDatabase` migrated only the guarded `tutorlink_test` database. No manual migrate/reset, seed, full suite, OpenAPI export, application runtime start, or HTTP smoke was run in D06-04.
- PHP syntax checks (`php -l`) for the two changed controllers and `ProgramLifecycleApiTest.php`: all three passed (exit 0).
- `graphify update .`: exit 0; graph rebuilt at 5,074 nodes / 11,500 edges.

- First focused attempt by task lead (exit 2): setup failed before reaching the request because fixture status `inactive` violated PostgreSQL `users_status_check`; no application behavior was evaluated. Fixture corrected to valid `suspended` status without changing DB constraint or business logic. The sanitized initial failure is retained as `focused-test-first.txt` and superseded by the passing rerun.
- After fixture correction, PHP syntax check for `ProgramLifecycleApiTest.php`: passed (exit 0).
- `graphify update .` rerun after correction: exit 0; graph rebuilt at 5,074 nodes / 11,501 edges.
