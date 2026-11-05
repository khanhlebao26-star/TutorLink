# D06-06 — Overview / Progress / Announcement / Fee metadata

- **Result:** Pass for the focused D04 metadata and regression scope.
- **Date:** 2026-11-04 (Asia/Ho_Chi_Minh).
- **Operations checked:** 16 total — Overview (1), Progress (3), Announcement (5), Fee (7).
- **Test target:** PostgreSQL 16 in isolated Compose project `tutorlink-d06-test`, database `tutorlink_test`, runner `tutorlink_test_runner`. The read-only guard returned SAFE before fixture prep and export.

## Metadata delta

Scramble had inferred `AnnouncementResource.files` as `string[]`, while the runtime returns a limited file object, and it did not expose the response enums for Progress `metric_name` or Fee `currency`, `status`, and `tutor_payment_mark`. PHPDoc on the resources did not change the generated schema, so the operation response types now declare these shapes explicitly.

- **Overview:** `next_session`, `latest_progress`, and `latest_announcement` are nullable; `task_counts` contains all five Task statuses; `capabilities` has six booleans. Existing request-ID response headers and 401/403/404/422 metadata remain present.
- **Progress:** response `metric_name` is constant `progress_percent`; `value` is integer; `note` is nullable. Collection keeps `page`/`per_page` limits and `meta(current_page,per_page,total,last_page)`. Mutation version, status, and request-ID metadata remain explicit.
- **Announcement:** `state` is `draft|published`; `files[]` is limited to `id`, `original_name`, `mime_type`, `size_bytes`, and `complete`. It does not expose storage keys or download URLs. Publish carries a UUID `Idempotency-Key`, version guard and 30-day replay policy; response status remains 200 for initial publish and matching replay. Published resources remain immutable.
- **Fee:** wire mapping is `period_start`/`period_end` → `period` (`YYYY-MM`), with integer `amount_minor`, fixed `currency: VND`, `status: declared|voided`, independent `tutor_payment_mark: unmarked|marked_paid|marked_unpaid`, `version`, payment/void timestamps and reasons, and the non-payment disclaimer. Fee writes require UUID keys and durable receipts with no time-based expiry; a matching create replay keeps 201. A zero amount remains unmarked and cannot be marked paid/unpaid.
- `docs/api/README.md` now carries the FE contract summary for capabilities/counts, file fields, fee state/payment mark, and the distinct receipt policies.

## Focused verification

- Six feature files ran on the seeded PostgreSQL test DB: `BeD04OverviewApiTest`, `BeD04ProgressApiTest`, `BeD04AnnouncementDraftApiTest`, `BeD04AnnouncementPublishApiTest`, `BeD04AnnouncementFileIntegrationTest`, and `BeD04FeeRecordApiTest`.
- Result: **18 passed, 534 assertions**. Coverage includes Owner/member capability values, zero/empty Overview, Progress pagination and integer/null values, member Draft denial, attachment allow-list, publish immutability/replay, duplicate month/stale Fee, zero-amount mark rejection, no-op receipts, and no-expiry Fee receipts.
- Scramble export completed from current source. Schema assertions passed for 123 operations, Overview capability count, Announcement file fields/state enum, Progress integer value, Fee VND/status/payment-mark enums, and UUID key metadata.
- `php -l` passed for all four D04 controllers after the final metadata changes.
- These are focused feature tests and generated-schema assertions; full D04 regression group, Redocly lint, route parity/CI, and browser HTTP smoke remain later D06 gates.

## Evidence files

- [Test database guard](guard.log)
- [D04 legacy fixture preparation](d04-legacy-prep.log), [schema migration](d04-migrate.log), [fixture seed](d04-seed.log)
- [Focused API test logs before final schema-only annotations](d04-focused-tests.log) and [final run](d04-focused-tests-final.log)
- [Final Scramble export](openapi-export-final.log)
- [Final OpenAPI schema assertions](openapi-schema-check-final.log)
- [PHP syntax checks](php-lint.log)
