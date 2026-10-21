# API contract

`openapi.json` is generated from registered Laravel `/api/v1` routes with Scramble. Treat it as a generated release artifact and export it from code instead of editing the JSON by hand.

This is the contract for the **currently registered API**, not the complete target design. The [API Route Catalog](<../Architecture & Technical Design/API Route Catalog.md>) lists both registered and planned routes with status; Product/Domain rules and PRDs define intended behavior. Scramble and route parity do not verify those business rules. `/sanctum/csrf-cookie` is registered by Sanctum outside `/api/v1` and is therefore absent from this file.

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
