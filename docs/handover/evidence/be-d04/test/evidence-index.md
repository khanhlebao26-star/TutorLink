# BE-D04 Test Evidence Index

All D04-01..20 test plan items are checked and linked in the [master plan](../../../BE-D04-plan.md). Artifact files are preserved in the grouped ZIP packages below; each package manifest records original paths and SHA-256 values.

| Nhóm | Nội dung | Gói và manifest |
|---|---|---|
| D04-01–06 | Baseline, test DB, fixtures, contract cases, schema/migration, nền API | [Tổng hợp](../consolidated/test-foundation-01-06.md) · [ZIP](../consolidated/test-foundation-01-06.zip) · [manifest](../consolidated/test-foundation-01-06.manifest.json) |
| D04-07–14 | Progress, Announcement, Fee, Overview, payload/list, idempotency/no-op | [Tổng hợp](../consolidated/test-api-implementation-07-14.md) · [ZIP](../consolidated/test-api-implementation-07-14.zip) · [manifest](../consolidated/test-api-implementation-07-14.manifest.json) |
| D04-15–20 | Race, fault/rollback, outbox/retention, regression, HTTP matrix, release | [Tổng hợp](../consolidated/test-assurance-release-15-20.md) · [ZIP](../consolidated/test-assurance-release-15-20.zip) · [manifest](../consolidated/test-assurance-release-15-20.manifest.json) |
| D04-22 pre-apply retest | Final candidate retest before applying local runtime | [Actual local D04-22](../consolidated/actual-local.md#d04-22) · [ZIP](../consolidated/actual-local.zip) · [manifest](../consolidated/actual-local.manifest.json) |

For acceptance totals see [BE-D04 test report](../../../BE-D04-test-report.md). Actual runtime evidence is indexed separately in the [actual evidence index](../actual/evidence-index.md).
