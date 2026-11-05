# BE-D06 — Release notes

## API contract

- Kept the exported API at **123 operations**. No route was added or removed in this D06 contract pass.
- Updated Scramble metadata for Program/Invitation, Task/Submission/feedback, Overview/Progress, Announcement, Fee, and marketplace lanes.
- Documented response shapes, nullable and enum fields, pagination, authorization and error branches, idempotency keys, version conflicts, and replay status behavior against source/tests.
- Task and Submission collections now have explicit pagination behavior. OpenAPI export and route parity are generated/checked from the guarded PostgreSQL test runner.
- `GET /listings/{slug}` now documents the observed 404 response for an unknown listing.
- Retained the Fee versus Announcement receipt policies: Fee write receipts have no time expiry; Announcement publish receipts retain for 30 days.
- Added a common framework error response envelope and coverage for framework-generated API errors.

Operation/schema detail: [OpenAPI operation and schema delta](openapi-operation-delta.md). Legacy field conversions and planned routes: [field mapping](../2026-11-05-034651/field-mapping.md) and [route status](../2026-11-05-034651/planned-routes.md).

## Runtime and test result

- Synthetic fixtures are reproducible in a disposable PostgreSQL test project and expose aliases rather than stable numeric IDs to FE examples.
- Final grouped regression on the D06 commit based on `be-k06`: 831 tests / 7,366 assertions; zero failures, errors, or skips. Line coverage is 93.59% (8,336/8,907).
- Live loopback cookie/CSRF HTTP smoke: 26/26 passed, with a database snapshot for Task/Submission/replay/feedback state.
- OpenAPI: 123/123 route parity, deterministic export hash, Redocly zero errors and 52 classified warnings.

See [test report](test-report.md), [runtime identity](runtime-identity.md), and [evidence index](evidence-index.md).

## Known follow-up gates

- The intended PR base is `be-k06` at `d157a8e`, not `main` at `fb54886`; local `be-d06` now descends from the K06 tip. GitHub publication and issue handoff are blocked: the connected `DungGiaIT` account can read the repository and reports `push: true`, but both Git Data blob creation and issue-comment creation returned HTTP 403 `Resource not accessible by integration`; shell Git has no username credential and `gh` is not installed. No remote D06 branch, PR, issue comment, CI run, or Khanh review request exists yet. See [publication blocker evidence](publication-blocker.md).
- Remote CI must pass on the final PR revision.
- Khanh's review must be requested and completed. Local review confirmation for D06-07 was already recorded; this does not replace the PR review gate.
- The operation inventory has 67 routes whose owner is still unconfirmed. The issue's owner-wide acceptance cannot be marked complete until the endpoint owners are identified and confirm their lane metadata; Dũng coordinates this follow-up.
- The authoritative System Design source cited by the contract remains unavailable. Dũng must locate it or retain the citation as unresolved before claiming full requirement traceability.
- Redocly's 47 missing-summary warnings remain pending owner confirmation; the other five warnings have documented runtime/configuration explanations.
- The System Design source file remains unavailable in the worktree and is recorded in the D06 gap register. The API docs do not claim the six planned Admin routes are callable.
- This release candidate does not claim production deployment, browser UI acceptance, or load testing.
