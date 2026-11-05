# D06-05 — Task / Submission metadata and pagination

- **Result:** Pass for focused Task/Submission contract work.
- **Date:** 2026-11-04 (Asia/Ho_Chi_Minh).
- **Scope:** 11 registered operations from `TaskController`: Task list/create/detail/update/start/cancel; current Submission, submission history, PUT Submission; feedback review/request-revision.
- **Runtime evidence:** PostgreSQL 16 isolated Compose test database, identity `tutorlink_test` / `tutorlink_test_runner`; read-only `test-console.php check` returned SAFE before the test run and each export attempt. The runner image was rebuilt from the current backend source.

## Changes

- Added explicit Scramble response shapes and status/error metadata for all 11 operations. The contract describes the Task, Submission, and feedback allow-lists; current Submission can be `null`; the error envelope makes `errors` optional.
- Documented `Idempotency-Key` as a required UUID on PUT Submission and both feedback actions. The generated schemas retain PUT 200 replay / 201 new version, expected version fields, and 409/422 branches.
- Implemented pagination for Task list and Submission history. Both accept `page` >= 1 and `per_page` 1–50, default to page 1 / 20, reject invalid query values with 422, and return `data` plus `meta.current_page`, `per_page`, `total`, and `last_page`.
- Preserved stable ordering: Task list by ascending ID; Submission history by descending `submission_version`. No approved route exception to BR-26/D07 pagination was found in the Catalog or decision register.
- Updated API Route Catalog and FE API guide with matching pagination and ordering rules.
- Exported `docs/api/openapi.json` from Scramble. The artifact still contains 123 operations; JSON remains generated, not hand-edited.

## Verification

- `php -l` passed for `TaskController.php` and `TaskWorkflowApiTest.php`.
- `TaskWorkflowApiTest.php`: **14 passed, 286 assertions** on the isolated PostgreSQL test runner.
- The added regression covers the default 20 item page, page 2, max page size 50, invalid `page`/`per_page`, descending Submission history pagination, and current Submission nullable feedback/reviewer fields.
- The initial test run had one incorrect assertion expecting a second page after requesting 50 items from a 21 item collection. The assertion was corrected to test page 2 at size 20 and size 50 separately; the rerun passed. Both logs are retained.
- The first export attempt could not resolve Scramble's configured relative docs path inside the container. The successful export mounted the repository `docs/api` at that path; the generated artifact was written by Scramble.
- This is Laravel feature-test evidence. It does not claim browser cookie/CSRF HTTP smoke, full suite, Redocly lint, route parity, or CI freshness; those remain later D06 gates.

## Files

- [TaskController.php](../../../../../backend/app/Http/Controllers/Api/V1/TaskController.php)
- [TaskWorkflowApiTest.php](../../../../../backend/tests/Feature/TaskWorkflowApiTest.php)
- [API Route Catalog](../../../../../docs/Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md)
- [API guide](../../../../../docs/api/README.md)
- [OpenAPI artifact](../../../../../docs/api/openapi.json)
- [Passing regression log](task-workflow-test-rerun.log)
- [Superseded initial regression log](task-workflow-test.log)
- [Database guard result](guard-before-test-and-export.log)
- [Final export log](openapi-export-final.log)
