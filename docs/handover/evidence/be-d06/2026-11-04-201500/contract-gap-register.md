# D06-03 — Contract gap register

- Captured: 2026-11-04 (Asia/Ho_Chi_Minh; exact time not recorded).
- OpenAPI artifact: `docs/api/openapi.json`, SHA-256 `83717e94538ac04fa6e4b7ec1865001f1dc5c810b67c5562e446ddce9b1c7940`, 123 operations.
- Laravel source route list: 132 routes total, 123 `/api/v1` pairs. Compared unique method/path sets after removing Laravel's `api/v1/` prefix and excluding HEAD aliases; missing=0, extra=0.
- Snapshot: `laravel-route-list.json`; SHA-256 `e53b0cdbe005fed3479664aa4e24d79907497a2c333a22daa48ae1b311e118b2`.

## Gaps and next steps

| ID | Impact | Owner | Next step |
|---|---|---|---|
| D06-03-01 | Resolved for method/path only: registered routes exactly match OpenAPI | Dũng (integration) | Re-run after route/source changes and at freeze. |
| D06-03-02 | High: inventory does not establish live payload, authorization, error or side-effect behavior | Endpoint owners + Dũng | Link focused tests and HTTP cases per operation during D06-04–13; preserve unverified behavior as gaps. |
| D06-03-03 | Medium: 67 operations have `owner=unconfirmed`; `module` is sourced from the first OpenAPI tag and is not owner proof | Endpoint owners + Dũng | Confirm each owner before release; unconfirmed rows carry `D06-03-OWNER`. |
| D06-03-04 | Medium: version/retry fields absent from OpenAPI are marked `not documented; needs source review`; pagination is artifact-level only | Endpoint owners + Dũng | Inspect source schemas/annotations and tests per route; update inventory after metadata work. |

## Inventory field interpretation

- `module` is the first OpenAPI tag; `owner` is `Dũng` only for lanes explicitly assigned to Dũng in the plan/contract (Program/Invitation, Task/Submission, D04 and marketplace); otherwise it is `unconfirmed`.
- `auth` reports OpenAPI declaration only: inherited global `sessionCookie` for operations without an override, or `public override (security: [])` for explicit empty overrides. All rows state behavior remains unverified. There are 11 explicit public overrides.
- `request_schema=none` means the operation has no OpenAPI request parameters or body. Response entries identify each 2xx status and schema/ref or `no content`; this records artifact metadata, not observed response bodies.
- `errors=not documented; needs source review` marks six operations with no error response in the artifact; it does not mean those endpoints cannot return errors. The Task GET cases are included in D06-04–07 source review.
- `version` and `retry` use `not documented; needs source review` where the artifact has no declaration; `none` is used only where the OpenAPI request shape is genuinely absent.

## Per-operation evidence gap

All 123 rows in `operation-inventory.csv` have `gap=D06-03-BEHAVIOR` and no focused test/HTTP case linked in this task. The generated artifact and route registration establish only method/path parity. They do not prove payload, auth, error, pagination, version, retry, or runtime behavior. No test suite or HTTP smoke was run.

## Counts

- OpenAPI artifact: 123 operations (checked-in artifact count, not fresh export).
- Catalog planned routes extracted: 15; kept separate in `planned-and-infrastructure-routes.md`.
- Infrastructure routes outside `/api/v1`: two, listed separately; not included in operation count.
- Laravel route-list parity: 123/123 API pairs; missing 0, extra 0.

## D06 closeout update (05/11/2026)

The observations above were captured before the D06 focused suites, final export, and live smoke. The following evidence supersedes only the corresponding baseline findings:

- **D06-03-01 — Route parity:** closed for the release candidate. A fresh guarded export contains 123 operations and Laravel parity passed 123/123; see [`openapi-route-parity-final.log`](../2026-11-05-0455/openapi-route-parity-final.log).
- **D06-03-02 — Behavior evidence:** partially addressed for Dũng's 56 assigned operations. [`operation-test-smoke-map-dung.csv`](../2026-11-05-0455/operation-test-smoke-map-dung.csv) links each assigned route to relevant feature-test files and identifies nine route templates with direct live HTTP cases. This is a navigation map to test files, not proof that every operation has an isolated request/response case. Other unverified behavior remains open.
- **D06-03-03 — Owner assignment:** **Open blocker.** 67 inventory rows still have `owner=unconfirmed`. Dũng coordinates identifying each endpoint owner and obtaining confirmation; no owner approval is inferred from the OpenAPI tag or this Dũng-lane test run.
- **D06-03-04 — Version/retry/schema evidence:** partially addressed in the D06-04–13 metadata, focused tests, OpenAPI, and HTTP evidence. Rows outside Dũng's confirmed lane remain subject to source/owner review.
- **Local validation:** export determinism, Redocly lint (zero errors; 52 warnings triaged), full grouped regression, and isolated HTTP smoke are recorded in [`test-report.md`](../2026-11-05-0455/test-report.md). Remote CI and Khanh's PR review are pending.

The authoritative System Design source cited by the D06 contract and the historical K03.1 report source remain unresolved in the separate baseline gap register. These are not treated as completed requirements; see [gap register](../2026-11-04-193718/gap-register.md). Do not close issue #86 or claim owner-wide acceptance until the explicit owner and source blockers are resolved.
