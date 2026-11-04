# BE-D06 — Test report

**Status:** Local final run passed. Remote CI and Khanh's review are pending the PR.
**Tested source baseline:** `d157a8eeae662c01d6bd6b031ea53c26a5b05688` plus the D06 working-tree patch described by the source files in this branch.
**Date:** 05/11/2026 (Asia/Ho_Chi_Minh).

## Regression and coverage

The final grouped run used an isolated PostgreSQL 16 test project and the repository test harness:

```sh
docker compose -p tutorlink-d06-final -f compose.testing.yml run --build --rm -T \
  -e TELESCOPE_ENABLED=false -e PULSE_ENABLED=false \
  -e MONITORING_ALERTS_ENABLED=false -e MONITORING_ALERTS_SUPPRESSED=true \
  backend-test
```

| Group | Tests | Assertions | Failures | Errors | Skipped |
| --- | ---: | ---: | ---: | ---: | ---: |
| D04 migration preflight | 7 | 27 | 0 | 0 | 0 |
| Backend regression | 746 | 5,369 | 0 | 0 | 0 |
| D04 API/contract | 77 | 1,077 | 0 | 0 | 0 |
| D04 PostgreSQL race | 1 | 893 | 0 | 0 | 0 |
| **Total** | **831** | **7,366** | **0** | **0** | **0** |

The race test uses independent PostgreSQL connections/processes and covers repeated lock-order cases. Reruns are preserved in the evidence directory but are not added to the total.

| Coverage view | Result | Basis |
| --- | ---: | --- |
| Backend lines | 93.59% (8,336/8,907) | Clover from this final grouped run |
| Backend methods | 79.97% (479/599) | Same Clover artifact |
| Backend classes | 61.78% (118/191) | Same Clover artifact |
| Dũng lane controller statements | 95.25% (1,805/1,895) | 56 inventory operations across 14 controller files; see the source mapping CSV |

Compared with K06's 827 tests and 93.58% line coverage (8,317/8,888), this run adds four tests and covers 19 more executable lines, a 0.01 percentage-point increase. The lane percentage is a source-statement mapping, not a claim that every operation has an individual test.

Evidence: [final runner log](full-suite-coverage-final.log), [preflight JUnit](coverage/d04-preflight-junit.xml), [regression JUnit](coverage/regression-junit.xml), [D04 JUnit](coverage/d04-junit.xml), [race JUnit](coverage/d04-race-junit.xml), [Clover](coverage/clover.xml), [text coverage](coverage/coverage.txt), [Dũng lane map](source-coverage-lane-dung.csv), [artifact checksums](coverage-artifact-sha256.txt).

For operation-level evidence navigation, [the Dũng lane map](operation-test-smoke-map-dung.csv) links all 56 assigned operations to controller/action-family feature-test files and marks the nine route templates with direct live HTTP cases. This file-level mapping helps locate evidence; it does not claim every operation has an isolated behavior test. Sixty-seven inventory operations remain `owner=unconfirmed` and need owner assignment/confirmation.

## HTTP smoke

The live loopback HTTP run passed **26/26** assertions using cookie jars, CSRF bootstrap, and the isolated fixture database. It covered 200, 201, 204, 401, 403, 404, 409, 419, 422, and 429 responses; replay identity, stale versions, idempotency-key mismatch, and final database state were checked. See [the sanitized HTTP transcript](../2026-11-05-0443/http-smoke.json), [database snapshot](../2026-11-05-0443/http-smoke-db-snapshot.txt), and [fixture manifest](../2026-11-05-0443/fixture-manifest-final.json).

## OpenAPI checks

The generated artifact contains 123 operations. Laravel route parity passed 123/123. Two exports from the guarded PostgreSQL runner had the same SHA-256: `2a75f1d738dd40f5e946d916bbdaa9c14067b10af4253406a2e88ab96d899046`. Redocly CLI 2.60.0 lint passed with zero errors and 52 warnings; the warnings are individually classified in [warning triage](redocly-warning-triage.md). The pinned CLI ran from the local npm cache after the registry lookup failed with `EAI_AGAIN`; both the failed lookup and successful lint logs are retained.

Evidence: [route parity](openapi-route-parity-final.log), [export](openapi-export-final.log), [lint](redocly-lint-smoke-final.log), [operation/schema delta](openapi-operation-delta.md), [lint warning triage](redocly-warning-triage.md).

## Limits

These results are local evidence for the recorded test image and isolated test database. They do not demonstrate production deployment, browser UI acceptance, load capacity, or remote CI. CI and reviewer approval remain external PR gates.
