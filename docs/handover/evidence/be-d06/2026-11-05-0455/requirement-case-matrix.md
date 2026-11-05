# BE-D06 — Requirement to case matrix

| Issue requirement | Verification cases | Evidence | Result |
| --- | --- | --- | --- |
| TODO 1 — Scramble metadata for each owner lane | 123-operation inventory; Program/Invitation, Task/Submission, Overview/Progress/Announcement/Fee and marketplace metadata reviews; focused feature tests | [Operation inventory](../2026-11-04-201500/operation-inventory.csv), [Dũng operation-to-test/smoke map](operation-test-smoke-map-dung.csv), [D06-04 case matrix](../2026-11-04-202539/case-matrix.md), [D06-05 metadata](../2026-11-04-204100/task-metadata.md), [D06-06 metadata](../2026-11-04-205328/d04-metadata.md), [D06-07/08 review](../2026-11-05-0438/focused-d06-07-08.log), [test report](test-report.md) | Dũng's 56 assigned operations are mapped to evidence; 67 other operations still lack confirmed owners, so owner-wide acceptance remains open. |
| TODO 2 — Generated OpenAPI, CI and registered route parity | Exported twice from guarded PostgreSQL; 123 OpenAPI operations compared with Laravel routes; Redocly lint; changed operation/schema delta | [Export hashes](openapi-after-final-export1.sha256), [route parity](openapi-route-parity-final.log), [lint and warning triage](redocly-warning-triage.md), [delta](openapi-operation-delta.md) | Local checks pass. Remote CI is pending the PR. |
| TODO 3 — Program/Task/Submission/Fee/cache tests, coverage and FE fixtures | Grouped regression; D04 rollback and race cases; Task pagination/current/history/replay; Fee amount/receipt/payment/void cases; marketplace/cache feature cases; synthetic fixture regeneration; HTTP state/replay assertions | [Test report](test-report.md), [coverage files](coverage/), [fixture guide](../2026-11-05-0443/fe-fixtures.md), [fixture manifest](../2026-11-05-0443/fixture-manifest-final.json), [HTTP transcript](../2026-11-05-0443/http-smoke.json), [DB snapshot](../2026-11-05-0443/http-smoke-db-snapshot.txt) | Local regression, fixture and HTTP checks pass. |
| TODO 4 — Release snapshot, legacy mapping and error/retry examples | Current field mappings; examples for 401/403/404/409/419/422/429 and 201→200 replay; source and operation delta; release manifest and evidence index | [Field mapping](../2026-11-05-034651/field-mapping.md), [FE examples](../2026-11-05-0443/examples/index.md), [HTTP transcript](../2026-11-05-0443/http-smoke.json), [release notes](release-notes.md), [evidence index](evidence-index.md) | Local evidence ready; release snapshot and PR linkage are recorded in the manifest/plan. |

## Representative HTTP cases

| Case | Expected and observed result | State assertion |
| --- | --- | --- |
| Health and authentication | `/up` 200; CSRF bootstrap 204; owner/member login 200; missing CSRF 419; anonymous Overview 401 | Cookies and CSRF values were held in memory and omitted from the transcript. |
| Program reads | Overview 200; Progress page 200 with 55 total rows and 28 pages | Synthetic late member reads the active Program scope. |
| Announcement and Fee reads | Published Announcement list/detail 200; Fee page 200 with 59 rows and 30 pages | Member cannot read Draft; Fee values remain integer minor units. |
| Task lifecycle | Task list/start 200; wrong role 403; unknown Task 404; stale Task version 409 | Only one Submission row is created for Task 1. |
| Submission and feedback | First submit 201; exact retry 200 with same ID; stale version and key/payload mismatch 409; invalid version 422; feedback/retry 200 | Final Submission is accepted at version 1, Task version is 4, and one feedback receipt exists. |
| Login throttling | Five synthetic invalid logins return 401; sixth returns 429 with `Retry-After: 60` | Probe uses an unseeded synthetic email. |

The HTTP transcript records actual requests against the local runtime. Invitation, Fee mutation, Announcement publication/file scan, cache invalidation, rollback, and deeper concurrency branches are backed by the feature-test groups rather than mislabeled as browser smoke. The old `D06-03-BEHAVIOR` register is updated by a closeout note after the evidence links; test-file mapping does not by itself close each operation's behavior gap.
