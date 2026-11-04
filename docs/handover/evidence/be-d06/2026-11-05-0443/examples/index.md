# D06 FE request and response examples

Every row links to a recorded result. Live HTTP cases are in [`http-smoke.json`](../http-smoke.json); cookies, session IDs, CSRF values and passwords are never written. The database state after replay and feedback is in [`http-smoke-db-snapshot.txt`](../http-smoke-db-snapshot.txt). The fixture IDs and aliases are in [`fixture-manifest-final.json`](../fixture-manifest-final.json).

| Case | Observed request/result | Source and scope |
| --- | --- | --- |
| CSRF bootstrap and login | `GET /sanctum/csrf-cookie` → 204; owner/member login → 200 | Real HTTP with independent in-memory cookie jars. Transcript has actor aliases and redacted login bodies. |
| Missing CSRF / unauthenticated | Login without XSRF header → 419 `csrf_token_mismatch`; anonymous Overview → 401 `unauthenticated` | Real HTTP, safe error envelopes and request IDs. |
| Program Overview / Progress | Overview → 200; Progress `per_page=2` → 200, `total=55`, `last_page=28` | Real HTTP; fixture actor is the active late member. |
| Announcement / file metadata | Published list and detail → 200; member list contains only the two published rows | Real HTTP. Draft remains owner-only. File scan/ownership allow-list cases are covered by the D04 grouped API cases linked from the [test report](../../2026-11-05-0455/test-report.md) and the D04 focused test log. |
| Fee records | Fee list `per_page=2` → 200, `total=59`, `last_page=30` | Real HTTP read and pagination. Create/replay/payment/void/zero-amount cases use the D04 grouped API tests; their distinct first-create/replay statuses are not presented as live-browser evidence. |
| Task and Submission lifecycle | Task list/start → 200; stale Task version → 409; first Submission → 201; exact replay → 200 with the same ID; stale version and same-key/different-payload → 409; invalid version → 422 | Real HTTP. The final DB snapshot records one Submission on Task 1 and no duplicate on replay. |
| Owner feedback | Review → 200; exact replay → 200 with the same Submission ID; key reuse with another payload → 409 | Real HTTP. Final DB row is `accepted`; one feedback idempotency receipt exists. |
| Authorization and throttling | Wrong actor → 403; unknown Task → 404; five synthetic invalid logins → 401, sixth → 429 with `Retry-After: 60` | Real HTTP; rate-limit probe uses an unseeded synthetic email. |
| Invitation send/replay/version | Initial send, exact retry, key mismatch and stale version | API feature cases: [`InvitationApiTest.php`](../../../../../../backend/tests/Feature/InvitationApiTest.php). This is automated API evidence, not a live cookie transcript. |
| Marketplace/cache | Public DTO, authenticated cache headers and cache invalidation | Existing feature tests and D06-07/08 source review; detailed evidence is linked in the [case matrix](../../2026-11-04-202539/case-matrix.md). |

The runnable HTTP harness is [`backend/scripts/be-d06-http-smoke.py`](../../../../../../backend/scripts/be-d06-http-smoke.py). It expects the synthetic D04 fixtures in the isolated `tutorlink_test` database and the loopback-only runtime from `compose.d06.runtime.testing.yml`. The saved JSON is an actual transcript, not an example assembled from OpenAPI or a mocked Laravel test response. Runtime image, PHP/Laravel/PostgreSQL versions and source hash checks are recorded in the [runtime identity report](../../2026-11-05-0455/runtime-identity.md); the tested source commit is in the [release manifest](../../2026-11-05-0455/release-manifest.json).
