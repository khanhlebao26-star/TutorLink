# D06-03 provenance

- Date: 2026-11-04 (Asia/Ho_Chi_Minh); exact capture time not present in runner output.
- Baseline commit: `d157a8eeae662c01d6bd6b031ea53c26a5b05688` from D06-01; D06-03 did not recapture HEAD.
- OpenAPI input: `docs/api/openapi.json`; bytes 1086888; SHA-256 `83717e94538ac04fa6e4b7ec1865001f1dc5c810b67c5562e446ddce9b1c7940`; 123 operations.
- Catalog input: `docs/Architecture & Technical Design/API Route Catalog.md`; rows labeled `Dự kiến` extracted to planned route list.
- Graphify queries (both exit 0): `graphify query "D06-03 operation inventory routes OpenAPI catalog owner"`; `graphify query "docs/api/openapi.json paths operations schemas security"`.
- Test DB guard: D06-02 evidence records `php scripts/test-console.php check` SAFE for isolated `tutorlink-d06-test` profile. No additional migration/reset/seed/test suite was run.
- Read-only Laravel route command (cwd repository root; exit 0): `docker compose -f compose.testing.yml -f compose.d06.testing.yml -p tutorlink-d06-test run --rm --no-deps backend-test php artisan route:list --json > outputs/be-d06/d06-03-routes.json 2> outputs/be-d06/d06-03-routes.stderr.log`.
- Route-list JSON copied from ignored `outputs/be-d06/d06-03-routes.json` to this directory as `laravel-route-list.json`; SHA-256 `e53b0cdbe005fed3479664aa4e24d79907497a2c333a22daa48ae1b311e118b2`.
- Parity method: parsed 132 route entries; retained `/api/v1/`, split method alternatives, excluded HEAD aliases, stripped `api/v1/` from URI; compared unique method/path pairs with OpenAPI. Result: 123 vs 123, missing 0, extra 0.
- No application runtime was started; no migrate, seed, suite, focused test, or HTTP smoke executed by D06-03.
- Limitations: owner derives from first OpenAPI tag; auth/schema/error/version/retry/pagination fields are artifact-level hints only and need per-operation confirmation. Behavior remains unverified.

## Reviewer-requested inventory detail revision

- Inventory now records each 2xx response status with its `$ref`, inline schema type/property shape, or `no content`; no response_schema cell is empty.
- Auth now distinguishes inherited global `sessionCookie` from all 11 explicit `security: []` public overrides, and labels behavior unverified.
- Added `module` from the OpenAPI tag. `owner` is assigned to Dũng only for plan/contract-explicit Program/Invitation, Task/Submission, D04, and marketplace lanes; the other 67 operations are `unconfirmed` and carry `D06-03-OWNER`.
- Request schema uses `none` when no OpenAPI parameters/body exist. Version/retry use `not documented; needs source review` when not declared by the artifact; any declaration remains marked for source behavior review. Method/path parity remains 123/123.

## Finalization record

- Evidence finalized at `2026-11-04T20:22:17+07:00` (Asia/Ho_Chi_Minh). This is the review/finalization time, not an invented exact time for the earlier route command. The ignored source route JSON file's filesystem mtime is `2026-11-04T20:13:02+07:00`.
- Rechecked branch `be-d06`, HEAD `d157a8eeae662c01d6bd6b031ea53c26a5b05688`; working tree remains dirty. `git diff --binary HEAD` (tracked changes) SHA-256 `898bc56db9712dcbddfe21ba20c3919d03b300c68d7af4e368f8366892868735` (1038 bytes). Untracked D06 artifacts and pre-existing D04 fixtures are separate from that tracked diff; their inputs are identified by file paths/checksums here and in D06-01/02 manifests.
- Runner image ID `sha256:1fb3547c839c02445392925b2c9b05469911f7d364e444bbed45ced01bc7a0b9`, PHP `8.3.36`; guarded PostgreSQL identity `tutorlink_test` / `tutorlink_test_runner` at `172.30.0.2`. See `../2026-11-04-195035/environment-manifest.json` for full image/DB evidence.
- Final inventory SHA-256 `24003008104b4ddfeaf02aae32dd559b9cb5c95fe270083abe00be9befac2402`; six OpenAPI operations without documented errors now say `not documented; needs source review` rather than implying no errors.
