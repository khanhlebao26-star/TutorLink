# API contract

`openapi.json` is generated from registered Laravel `/api/v1` routes with Scramble. Treat it as a generated release artifact and export it from code instead of editing the JSON by hand.

This is the contract for the **currently registered API**, not the complete target design. The [API Route Catalog](<../Architecture & Technical Design/API Route Catalog.md>) lists both registered and planned routes with status; Product/Domain rules and PRDs define intended behavior. Scramble and route parity do not verify those business rules. `GET /sanctum/csrf-cookie` and `POST /broadcasting/auth` are infrastructure routes outside `/api/v1` and are therefore absent from this file. K03.11 verifies the latter with session/CSRF, current account/membership, signed private-channel authorization and live Reverb integration; it does not add an OpenAPI operation or certify socket revocation after subscribe.

Authentication uses a Sanctum session cookie. A browser first calls `GET /sanctum/csrf-cookie`, then sends credentials with the session cookie. State-changing requests also send the `X-XSRF-TOKEN` header derived from the `XSRF-TOKEN` cookie. The generated `sessionCookie` security scheme marks routes requiring a session; public routes remain unauthenticated. Missing authentication returns 401; an invalid or expired CSRF token returns 419. Do not automatically replay a state-changing request after 419. The session cookie name comes from Laravel's `session.cookie` configuration at export time. The API contract does not advertise token authentication.

From `backend/`, run:

```sh
export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/tutorlink-openapi.sqlite
touch "$DB_DATABASE"
php artisan migrate --force --no-interaction
php artisan scramble:export --no-interaction
npx --yes @redocly/cli@2.60.0 lint ../docs/api/openapi.json --config ../.redocly.yaml
php scripts/verify-openapi-routes.php
```

CI validates PostgreSQL migrations separately, then exports against a migrated temporary SQLite database so schema inference produces the same artifact locally and in CI. It validates the document, compares its operations with registered routes, and checks that the committed artifact is current. Add request validation, response resource/type, authentication, summaries, and error metadata alongside each endpoint change before refreshing the artifact. Redocly keeps incomplete summaries and response details as warnings while schema and reference errors remain CI failures.

The seven D23 Admin endpoints remain in the target [API Route Catalog](<../Architecture & Technical Design/API Route Catalog.md>). They are planned, not registered yet, so they are absent from this generated contract. Settle each endpoint's permission, scope, request/response and audit behavior before implementation; then register the route, test it and regenerate this contract. Do not hide a registered route from the contract merely because implementation is incomplete.

## BE-H02 integration

H02 adds `PUT /api/v1/admin/tutor-profiles/{tutorProfile}/assignment` and the two `POST /api/v1/admin/verification-documents/{document}/{approve|reject}` routes. These require the new `admin.tutor_profiles.assign` and `admin.verification_documents.decide` permissions respectively. Existing Admin accounts are not automatically granted either permission; the relevant coordinator/identity owner must provision them through the agreed account-management process.

Assignment requests contain `assigned_admin_id` (nullable for revocation), the current `assignment_version`, and a nonblank `reason` (2–2,000 characters). The selected Admin must be active and have profile view/decision permissions. The response contains assignment metadata only. Reassign/revoke increments `assignment_version`; stale requests return `409 stale_assignment_version`. Assignment permission alone does not grant profile, document, or decision access.

Profile and document decisions require `assignment_version` and `reason`. A profile revision decision additionally supplies `expected_version` for the submitted revision. Profile decisions require current assignment and `admin.tutor_profiles.decide`; document decisions require current assignment and the separate document-decision permission. Out-of-scope profile reads/decisions return 404; missing action permissions return 403. File download still requires profile view, current assignment and the separate document-download permission. Reviewer metadata access and downloads are audited.

`GET /api/v1/admin/tutor-profiles` defaults to `scope=mine` for viewers and `scope=unassigned` for assignment-only coordinators. `scope=all` and `scope=unassigned` require assignment permission and return minimal assignment metadata. Scope filters apply before pagination/counts; the response includes `meta.current_page`, `meta.total`, and `meta.per_page`.

Tutor submit now requires a complete owned avatar, active specialization, headline/bio and a complete private identity card/passport; structured `qualification_claims` require corresponding degree/certificate evidence. Claims contain only `type` (`degree`/`certificate`) and `name`. Profile approval requires approved, unexpired evidence; `other` is not identity evidence. Document replacement creates a separate row and preserves existing decisions.

For an approved profile, PUT profile/avatar edits create a private revision and leave the approved profile unchanged. The first edit omits `expected_version` or sends 0; further edits send the current revision `version`. Tutor GET profile returns the approved fields and the latest `revision` with its `payload`, `status`, and `version`. Submit changes the editable revision to submitted and increments its version. Approve copies the reviewed payload into the live profile atomically; request-changes/reject preserve the live profile. Rejected profiles/revisions cannot self-resubmit. A still eligible assigned reviewer is retained on resubmit; otherwise the profile returns to the unassigned queue with an incremented assignment version.

Profile suspension requires profile permission; if the account is active it additionally requires account-suspension permission and changes both resources, audit, token/session revocation and the profile outbox record in one database transaction. Restore uses `status_before_suspension` and leaves account restoration separate. Suspension/restore does not require assignment. These responses intentionally contain only profile/account state. The H02 migration backfills previously suspended profiles as approved because the legacy endpoint could suspend only approved profiles. No migration or deployment on shared/staging/production databases is implied by test evidence.

H02 PostgreSQL regression coverage includes concurrent assignment writers and Accept-vs-atomic-profile-suspend using independent processes. Listing moderation races and Session/Task writer races remain with their respective modules. Public marketplace visibility/cache/query work remains BE-D05; the H02 checklist and Program eligibility checks do not certify those public surfaces.
